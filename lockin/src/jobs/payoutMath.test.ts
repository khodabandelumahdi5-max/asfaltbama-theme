import assert from "node:assert/strict";
import { test } from "node:test";
import { chunk, computeSettlement } from "./payoutMath.ts";

const STAKE = 50_000_000n; // 0.05 SOL

function reconciles(s: ReturnType<typeof computeSettlement>) {
  assert.equal(s.platformFee + s.dust + s.perSurvivor * BigInt(s.survivorCount), s.total);
}

test("10 stakes, 4 survivors: 10% fee, 90% split evenly", () => {
  const s = computeSettlement(10n * STAKE, 4);
  assert.equal(s.platformFee, 50_000_000n);
  assert.equal(s.perSurvivor, 112_500_000n);
  assert.equal(s.dust, 0n);
  reconciles(s);
});

test("uneven split sends the remainder to the platform as dust", () => {
  const s = computeSettlement(10n * STAKE, 7);
  assert.equal(s.perSurvivor, 450_000_000n / 7n);
  assert.ok(s.dust > 0n && s.dust < 7n);
  reconciles(s);
});

test("fee floors on odd totals", () => {
  const s = computeSettlement(999n, 1);
  assert.equal(s.platformFee, 99n);
  assert.equal(s.perSurvivor, 900n);
  reconciles(s);
});

test("everyone survives: each gets 90% of their stake back", () => {
  const s = computeSettlement(5n * STAKE, 5);
  assert.equal(s.perSurvivor, 45_000_000n);
  reconciles(s);
});

test("zero survivors: whole pool goes to the platform", () => {
  const s = computeSettlement(3n * STAKE, 0);
  assert.equal(s.perSurvivor, 0n);
  assert.equal(s.platformFee + s.dust, 3n * STAKE);
});

test("empty pool settles to zero", () => {
  const s = computeSettlement(0n, 0);
  assert.equal(s.platformFee + s.dust, 0n);
});

test("rejects invalid input", () => {
  assert.throws(() => computeSettlement(-1n, 1), RangeError);
  assert.throws(() => computeSettlement(1n, -1), RangeError);
  assert.throws(() => computeSettlement(1n, 1.5), RangeError);
});

test("chunk splits into fixed-size batches", () => {
  assert.deepEqual(chunk([1, 2, 3, 4, 5], 2), [[1, 2], [3, 4], [5]]);
  assert.deepEqual(chunk([], 3), []);
});
