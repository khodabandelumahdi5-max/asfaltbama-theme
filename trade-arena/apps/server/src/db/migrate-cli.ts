import { closeDb } from "./client";
import { runMigrations } from "./migrate";

runMigrations();
closeDb();
console.log("Migrations applied");
