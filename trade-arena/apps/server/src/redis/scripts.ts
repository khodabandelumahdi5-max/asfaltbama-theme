/**
 * Atomic trading primitives executed inside Redis. All numeric return values are
 * converted with tostring() because Redis truncates Lua numbers to integers.
 */

/**
 * KEYS: 1 acct, 2 pos, 3 liqLong, 4 liqShort, 5 meta
 * ARGV: 1 uid, 2 tradeId, 3 side, 4 leverage, 5 margin, 6 price, 7 feeRate, 8 mmr, 9 nowMs
 * Returns: {err} or {"OK", qty, liq, fee, balance}
 */
export const OPEN_POSITION_LUA = `
local status = redis.call('HGET', KEYS[5], 'status')
if status ~= 'ACTIVE' then return {'ERR', 'TOURNAMENT_NOT_ACTIVE'} end
local endsAt = tonumber(redis.call('HGET', KEYS[5], 'endsAt'))
if endsAt and tonumber(ARGV[9]) >= endsAt then return {'ERR', 'TOURNAMENT_NOT_ACTIVE'} end
if redis.call('EXISTS', KEYS[1]) == 0 then return {'ERR', 'NOT_JOINED'} end
if redis.call('EXISTS', KEYS[2]) == 1 then return {'ERR', 'POSITION_EXISTS'} end

local side = ARGV[3]
local lev = tonumber(ARGV[4])
local margin = tonumber(ARGV[5])
local price = tonumber(ARGV[6])
local feeRate = tonumber(ARGV[7])
local mmr = tonumber(ARGV[8])

local notional = margin * lev
local qty = notional / price
local fee = notional * feeRate
local bal = tonumber(redis.call('HGET', KEYS[1], 'bal'))
if bal < margin + fee then return {'ERR', 'INSUFFICIENT_BALANCE'} end

local liq
if side == 'LONG' then
  liq = price * (1 - 1 / lev + mmr)
  redis.call('ZADD', KEYS[3], liq, ARGV[1])
else
  liq = price * (1 + 1 / lev - mmr)
  redis.call('ZADD', KEYS[4], liq, ARGV[1])
end

redis.call('HSET', KEYS[2],
  'tradeId', ARGV[2], 'side', side, 'lev', ARGV[4], 'margin', ARGV[5],
  'qty', tostring(qty), 'entry', ARGV[6], 'liq', tostring(liq), 'fee', tostring(fee), 'openedAt', ARGV[9])
local newBal = redis.call('HINCRBYFLOAT', KEYS[1], 'bal', tostring(-(margin + fee)))
return {'OK', tostring(qty), tostring(liq), tostring(fee), newBal}
`;

/**
 * KEYS: 1 acct, 2 pos, 3 liqLong, 4 liqShort, 5 lb, 6 lbDirty
 * ARGV: 1 uid, 2 exitPrice, 3 feeRate, 4 reason ('MANUAL'|'LIQUIDATION'|'TOURNAMENT_END')
 * Returns: nil (no position) or flat array of position + result fields as strings.
 */
export const CLOSE_POSITION_LUA = `
if redis.call('EXISTS', KEYS[2]) == 0 then return false end
local p = redis.call('HGETALL', KEYS[2])
local pos = {}
for i = 1, #p, 2 do pos[p[i]] = p[i + 1] end

local side = pos['side']
local qty = tonumber(pos['qty'])
local entry = tonumber(pos['entry'])
local margin = tonumber(pos['margin'])
local openFee = tonumber(pos['fee'])
local exitPrice = tonumber(ARGV[2])
local feeRate = tonumber(ARGV[3])
local reason = ARGV[4]

local dir = 1
if side == 'SHORT' then dir = -1 end

local closeFee = 0
local payout = 0
if reason == 'LIQUIDATION' then
  exitPrice = tonumber(pos['liq'])
  payout = 0
else
  local gross = (exitPrice - entry) * qty * dir
  closeFee = qty * exitPrice * feeRate
  payout = margin + gross - closeFee
  if payout < 0 then payout = 0 end
end
local realized = payout - margin - openFee

local bal = tonumber(redis.call('HINCRBYFLOAT', KEYS[1], 'bal', tostring(payout)))
redis.call('HINCRBY', KEYS[1], 'trades', 1)
if realized > 0 then redis.call('HINCRBY', KEYS[1], 'wins', 1) end
local start = tonumber(redis.call('HGET', KEYS[1], 'start'))
local pnlPct = (bal - start) / start * 100

redis.call('DEL', KEYS[2])
redis.call('ZREM', KEYS[3], ARGV[1])
redis.call('ZREM', KEYS[4], ARGV[1])
redis.call('ZADD', KEYS[5], pnlPct, ARGV[1])
redis.call('SET', KEYS[6], '1')

return {
  pos['tradeId'], side, pos['lev'], pos['margin'], pos['qty'], pos['entry'], pos['liq'], pos['fee'], pos['openedAt'],
  tostring(exitPrice), tostring(closeFee), tostring(realized), tostring(bal), tostring(pnlPct)
}
`;

/**
 * KEYS: 1 acct, 2 lb, 3 players, 4 lbDirty
 * ARGV: 1 uid, 2 startingBalance
 * Returns 1 if newly joined, 0 if already joined.
 */
export const JOIN_TOURNAMENT_LUA = `
if redis.call('EXISTS', KEYS[1]) == 1 then return 0 end
redis.call('HSET', KEYS[1], 'bal', ARGV[2], 'start', ARGV[2], 'trades', 0, 'wins', 0)
redis.call('ZADD', KEYS[2], 0, ARGV[1])
redis.call('SADD', KEYS[3], ARGV[1])
redis.call('SET', KEYS[4], '1')
return 1
`;

/**
 * Compare-and-renew leader lock. KEYS: 1 lock  ARGV: 1 instanceId, 2 ttlMs
 */
export const RENEW_LOCK_LUA = `
if redis.call('GET', KEYS[1]) == ARGV[1] then
  return redis.call('PEXPIRE', KEYS[1], ARGV[2])
end
return 0
`;

/** Compare-and-delete leader lock. KEYS: 1 lock  ARGV: 1 instanceId */
export const RELEASE_LOCK_LUA = `
if redis.call('GET', KEYS[1]) == ARGV[1] then
  return redis.call('DEL', KEYS[1])
end
return 0
`;
