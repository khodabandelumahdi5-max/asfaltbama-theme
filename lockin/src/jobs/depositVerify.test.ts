import assert from "node:assert/strict";
import { test } from "node:test";
import { Keypair, PublicKey, SystemProgram } from "@solana/web3.js";
import { checkDeposit, sumSystemTransfers, type DecodedInstruction } from "./depositVerify.ts";

const user = Keypair.generate().publicKey;
const escrow = Keypair.generate().publicKey;
const other = Keypair.generate().publicKey;

function decoded(ix: { programId: PublicKey; keys: { pubkey: PublicKey }[]; data: Buffer }): DecodedInstruction {
  return { programId: ix.programId, accounts: ix.keys.map((k) => k.pubkey), data: ix.data };
}
const transfer = (from: PublicKey, to: PublicKey, lamports: number) =>
  decoded(SystemProgram.transfer({ fromPubkey: from, toPubkey: to, lamports }));

test("sums only transfers from the sender to escrow", () => {
  const ixs = [
    transfer(user, escrow, 20_000_000),
    transfer(user, other, 99),
    transfer(other, escrow, 77),
    transfer(user, escrow, 30_000_000),
  ];
  assert.equal(sumSystemTransfers(ixs, user.toBase58(), escrow.toBase58()), 50_000_000n);
});

test("ignores non-transfer system instructions and other programs", () => {
  const create = decoded(
    SystemProgram.createAccount({ fromPubkey: user, newAccountPubkey: escrow, lamports: 5, space: 0, programId: other }),
  );
  const memo = { programId: other, accounts: [user, escrow], data: new Uint8Array([2, 0, 0, 0, 1, 2, 3, 4, 5, 6, 7, 8]) };
  assert.equal(sumSystemTransfers([create, memo], user.toBase58(), escrow.toBase58()), 0n);
});

const base = { expectedLamports: 50n, receivedAt: new Date(0), now: new Date(60_000), missingAfterMs: 600_000 };

test("missing transaction: retry while young, hold when old", () => {
  assert.deepEqual(checkDeposit({ ...base, found: null }), { ok: false, retryLater: true, reason: "deposit not finalized yet" });
  const old = checkDeposit({ ...base, found: null, now: new Date(700_000) });
  assert.equal(old.ok, false);
  assert.equal(!old.ok && old.retryLater, false);
});

test("failed or mismatched deposits are held", () => {
  const failed = checkDeposit({ ...base, found: { err: { x: 1 }, transferred: 50n } });
  assert.equal(failed.ok === false && failed.retryLater === false, true);
  const wrong = checkDeposit({ ...base, found: { err: null, transferred: 49n } });
  assert.equal(wrong.ok === false && wrong.retryLater === false, true);
});

test("exact match passes", () => {
  assert.deepEqual(checkDeposit({ ...base, found: { err: null, transferred: 50n } }), { ok: true });
});
