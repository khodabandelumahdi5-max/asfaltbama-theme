CREATE TABLE `deposits` (
	`id` text PRIMARY KEY NOT NULL,
	`reference` text NOT NULL,
	`user_id` text NOT NULL,
	`network` text NOT NULL,
	`asset` text DEFAULT 'USDT' NOT NULL,
	`address` text NOT NULL,
	`amount_micro` integer NOT NULL,
	`received_micro` integer,
	`tickets` integer NOT NULL,
	`status` text NOT NULL,
	`tx_hash` text,
	`expires_at` integer NOT NULL,
	`confirmed_at` integer,
	`created_at` integer DEFAULT (unixepoch('subsec') * 1000) NOT NULL,
	FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE UNIQUE INDEX `deposits_reference_uq` ON `deposits` (`reference`);--> statement-breakpoint
CREATE UNIQUE INDEX `deposits_tx_hash_uq` ON `deposits` (`tx_hash`);--> statement-breakpoint
CREATE UNIQUE INDEX `deposits_pending_amount_uq` ON `deposits` (`network`,`amount_micro`) WHERE status = 'PENDING';--> statement-breakpoint
CREATE INDEX `deposits_user_idx` ON `deposits` (`user_id`,`created_at`);--> statement-breakpoint
CREATE TABLE `ledger` (
	`id` integer PRIMARY KEY AUTOINCREMENT NOT NULL,
	`user_id` text NOT NULL,
	`delta` integer NOT NULL,
	`balance_after` integer NOT NULL,
	`kind` text NOT NULL,
	`ref_id` text,
	`created_at` integer DEFAULT (unixepoch('subsec') * 1000) NOT NULL,
	FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE INDEX `ledger_user_idx` ON `ledger` (`user_id`,`created_at`);--> statement-breakpoint
CREATE UNIQUE INDEX `ledger_kind_ref_user_uq` ON `ledger` (`kind`,`ref_id`,`user_id`);--> statement-breakpoint
CREATE TABLE `tournament_entries` (
	`id` integer PRIMARY KEY AUTOINCREMENT NOT NULL,
	`tournament_id` text NOT NULL,
	`user_id` text NOT NULL,
	`starting_balance` real NOT NULL,
	`final_balance` real,
	`final_pnl_pct` real,
	`final_rank` integer,
	`prize_tickets` integer DEFAULT 0 NOT NULL,
	`trades` integer DEFAULT 0 NOT NULL,
	`created_at` integer DEFAULT (unixepoch('subsec') * 1000) NOT NULL,
	FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON UPDATE no action ON DELETE no action,
	FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE UNIQUE INDEX `entries_tournament_user_uq` ON `tournament_entries` (`tournament_id`,`user_id`);--> statement-breakpoint
CREATE INDEX `entries_user_idx` ON `tournament_entries` (`user_id`);--> statement-breakpoint
CREATE TABLE `tournaments` (
	`id` text PRIMARY KEY NOT NULL,
	`name` text NOT NULL,
	`status` text NOT NULL,
	`starts_at` integer NOT NULL,
	`ends_at` integer NOT NULL,
	`entry_tickets` integer NOT NULL,
	`starting_balance` real NOT NULL,
	`players` integer DEFAULT 0 NOT NULL,
	`prize_pool_tickets` integer DEFAULT 0 NOT NULL,
	`final_price` real,
	`finished_at` integer,
	`created_at` integer DEFAULT (unixepoch('subsec') * 1000) NOT NULL
);
--> statement-breakpoint
CREATE INDEX `tournaments_status_idx` ON `tournaments` (`status`,`ends_at`);--> statement-breakpoint
CREATE TABLE `trades` (
	`id` text PRIMARY KEY NOT NULL,
	`tournament_id` text NOT NULL,
	`user_id` text NOT NULL,
	`symbol` text NOT NULL,
	`side` text NOT NULL,
	`leverage` integer NOT NULL,
	`margin` real NOT NULL,
	`qty` real NOT NULL,
	`entry_price` real NOT NULL,
	`liquidation_price` real NOT NULL,
	`open_fee` real NOT NULL,
	`exit_price` real,
	`close_fee` real,
	`realized_pnl` real,
	`close_reason` text,
	`status` text NOT NULL,
	`opened_at` integer NOT NULL,
	`closed_at` integer,
	FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON UPDATE no action ON DELETE no action,
	FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE INDEX `trades_user_idx` ON `trades` (`user_id`,`opened_at`);--> statement-breakpoint
CREATE INDEX `trades_tournament_idx` ON `trades` (`tournament_id`,`status`);--> statement-breakpoint
CREATE TABLE `users` (
	`id` text PRIMARY KEY NOT NULL,
	`username` text,
	`first_name` text NOT NULL,
	`last_name` text,
	`language_code` text,
	`photo_url` text,
	`is_premium` integer DEFAULT false NOT NULL,
	`referral_code` text NOT NULL,
	`referred_by` text,
	`tickets` integer DEFAULT 0 NOT NULL,
	`last_seen_at` integer NOT NULL,
	`created_at` integer DEFAULT (unixepoch('subsec') * 1000) NOT NULL
);
--> statement-breakpoint
CREATE UNIQUE INDEX `users_referral_code_uq` ON `users` (`referral_code`);--> statement-breakpoint
CREATE INDEX `users_referred_by_idx` ON `users` (`referred_by`);