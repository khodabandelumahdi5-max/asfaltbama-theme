import assert from "node:assert/strict";
import { test } from "node:test";
import { classifyAttempt, clusterName, leavesValidBalance } from "./payoutOutcome.ts";

const base = { finalizedBlockHeight: 100, lastValidBlockHeight: 150 };

test("finalized without error is CONFIRMED", () => {
  assert.equal(classifyAttempt({ ...base, status: { confirmationStatus: "finalized", err: null } }), "CONFIRMED");
});

test("finalized with error is FAILED", () => {
  assert.equal(
    classifyAttempt({ ...base, status: { confirmationStatus: "finalized", err: { InstructionError: [0, "x"] } } }),
    "FAILED",
  );
});

test("processed or confirmed is still IN_FLIGHT, success or not", () => {
  for (const confirmationStatus of ["processed", "confirmed", null]) {
    assert.equal(classifyAttempt({ ...base, status: { confirmationStatus, err: null } }), "IN_FLIGHT");
    assert.equal(classifyAttempt({ ...base, status: { confirmationStatus, err: "boom" } }), "IN_FLIGHT");
  }
});

test("unknown signature is IN_FLIGHT until the blockhash is past its last valid height", () => {
  assert.equal(classifyAttempt({ status: null, finalizedBlockHeight: 150, lastValidBlockHeight: 150 }), "IN_FLIGHT");
  assert.equal(classifyAttempt({ status: null, finalizedBlockHeight: 151, lastValidBlockHeight: 150 }), "EXPIRED");
});

test("a landed transaction is never EXPIRED, even past the last valid height", () => {
  const past = { finalizedBlockHeight: 999, lastValidBlockHeight: 150 };
  assert.equal(classifyAttempt({ ...past, status: { confirmationStatus: "confirmed", err: null } }), "IN_FLIGHT");
  assert.equal(classifyAttempt({ ...past, status: { confirmationStatus: "finalized", err: null } }), "CONFIRMED");
});

test("cluster detection", () => {
  assert.equal(clusterName("EtWTRABZaYq6iMfeYKouRu166VU2xqa1wcaWoxPkrZBG"), "devnet");
  assert.equal(clusterName("5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d"), "mainnet");
  assert.equal(clusterName("abc"), "localnet/unknown");
});

test("escrow must end empty or rent-exempt", () => {
  const rent = 890_880n;
  assert.equal(leavesValidBalance(1_000n, 1_000n, rent), true);
  assert.equal(leavesValidBalance(2_000_000n, 1_000_000n, rent), true);
  assert.equal(leavesValidBalance(1_000_000n, 500_000n, rent), false);
  assert.equal(leavesValidBalance(1_000n, 2_000n, rent), false);
});
