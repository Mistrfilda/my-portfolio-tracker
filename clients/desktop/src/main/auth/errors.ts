import type { SessionIssue } from '../../shared/session';

export class AuthError extends Error {
	constructor(readonly issue: SessionIssue | 'cancelled') {
		super('Desktop authentication failed: ' + issue);
	}
}

export function sessionIssue(error: unknown): SessionIssue | null {
	if (error instanceof AuthError)
		return error.issue === 'cancelled' ? null : error.issue;
	return 'server';
}
