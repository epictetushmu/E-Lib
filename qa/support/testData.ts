import { randomUUID } from 'node:crypto';

/**
 * Unique-per-run test data. Every value embeds a UUID fragment so parallel
 * workers and repeat runs never collide on unique indexes (e.g. Users.email).
 */

export function uniqueSuffix(): string {
  return randomUUID().slice(0, 8);
}

export function uniqueEmail(prefix = 'qa-user'): string {
  return `${prefix}-${uniqueSuffix()}@e2e.e-lib.test`;
}

export function uniqueUsername(prefix = 'qa_user'): string {
  return `${prefix}_${uniqueSuffix()}`;
}

/** Satisfies both server-side (>= 8 chars) and client-side (digit + symbol) password rules. */
export function validPassword(): string {
  return `Qa-${uniqueSuffix()}9!`;
}

export function uniqueBookTitle(prefix = 'QA Test Book'): string {
  return `${prefix} ${uniqueSuffix()}`;
}

export interface TestUser {
  username: string;
  email: string;
  password: string;
}

export function buildTestUser(prefix = 'qa'): TestUser {
  return {
    username: uniqueUsername(prefix),
    email: uniqueEmail(prefix),
    password: validPassword(),
  };
}
