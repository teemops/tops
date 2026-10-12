import { env, createExecutionContext, waitOnExecutionContext } from 'cloudflare:test';
import { beforeEach, describe, expect, it } from 'vitest';

import worker from '../src/index';

const ENDPOINT = 'https://teemops.com/api/design-partner';

/** Mirrors schema.sql. Applied per test file because D1 starts empty here. */
const SCHEMA = `
CREATE TABLE IF NOT EXISTS design_partner_leads (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name       TEXT NOT NULL,
  email      TEXT NOT NULL,
  company    TEXT,
  aws_scale  TEXT,
  notes      TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);`;

/**
 * Stands in for the EMAIL binding, so tests record what would be sent rather
 * than sending it. Set `failNext` to make the next send throw like a real
 * binding error does.
 */
const email = {
	sent: [] as EmailMessageBuilder[],
	failNext: false,
	async send(message: EmailMessageBuilder): Promise<EmailSendResult> {
		if (email.failNext) {
			email.failNext = false;
			throw Object.assign(new Error('delivery failed'), { code: 'E_DELIVERY_FAILED' });
		}
		email.sent.push(message);
		return { messageId: 'test' };
	},
};

async function post(fields: Record<string, string>, method = 'POST'): Promise<Response> {
	const body = new FormData();
	for (const [k, v] of Object.entries(fields)) body.append(k, v);

	const request = new Request(ENDPOINT, {
		method,
		body: method === 'POST' ? body : undefined,
	});
	const ctx = createExecutionContext();
	const response = await worker.fetch(request, { ...env, EMAIL: email as unknown as SendEmail }, ctx);
	await waitOnExecutionContext(ctx);
	return response;
}

async function leadCount(): Promise<number> {
	const row = await env.LEADS_DB.prepare(
		'SELECT count(*) AS n FROM design_partner_leads'
	).first<{ n: number }>();
	return row?.n ?? 0;
}

const VALID = {
	name: 'Grace Hopper',
	email: 'grace@example.com',
	company: 'Navy',
	aws_scale: '2-5',
	notes: 'Worried about IAM sprawl.',
};

beforeEach(async () => {
	await env.LEADS_DB.exec(SCHEMA.replace(/\n/g, ' '));
	await env.LEADS_DB.exec('DELETE FROM design_partner_leads');
	email.sent = [];
	email.failNext = false;
});

describe('POST /api/design-partner', () => {
	it('stores a valid submission', async () => {
		const res = await post(VALID);

		expect(res.status).toBe(201);
		await expect(res.json()).resolves.toEqual({ ok: true });

		const row = await env.LEADS_DB.prepare(
			'SELECT name, email, company, aws_scale, notes FROM design_partner_leads'
		).first();
		expect(row).toMatchObject({
			name: 'Grace Hopper',
			email: 'grace@example.com',
			company: 'Navy',
			aws_scale: '2-5',
			notes: 'Worried about IAM sprawl.',
		});
	});

	it('accepts a submission with only the required fields', async () => {
		const res = await post({ name: 'Ada', email: 'ada@example.com' });

		expect(res.status).toBe(201);
		expect(await leadCount()).toBe(1);
	});

	it('trims whitespace and caps over-long fields', async () => {
		await post({ ...VALID, name: '  Grace  ', notes: 'x'.repeat(5000) });

		const row = await env.LEADS_DB.prepare(
			'SELECT name, notes FROM design_partner_leads'
		).first<{ name: string; notes: string }>();
		expect(row?.name).toBe('Grace');
		expect(row?.notes).toHaveLength(2000);
	});
});

describe('notification', () => {
	it('emails the lead to the inbox, with replies going to the lead', async () => {
		await post(VALID);

		expect(email.sent).toHaveLength(1);
		const [message] = email.sent;
		expect(message.from).toEqual({ email: 'leads@notify.teemops.com', name: 'teemops.com' });
		expect(message.to).toBe('help@teemops.com');
		expect(message.replyTo).toEqual({ email: 'grace@example.com', name: 'Grace Hopper' });
		expect(message.subject).toBe('Demo request: Grace Hopper (Navy)');
		expect(message.text).toContain('Worried about IAM sprawl.');
	});

	it('still stores the lead and answers 201 when the email fails', async () => {
		email.failNext = true;

		const res = await post(VALID);

		expect(res.status).toBe(201);
		expect(await leadCount()).toBe(1);
	});

	it('sends nothing for a rejected or trapped submission', async () => {
		await post({ ...VALID, email: 'not-an-email' });
		await post({ ...VALID, website: 'http://spam.example' });

		expect(email.sent).toHaveLength(0);
	});
});

describe('honeypot', () => {
	it('drops a submission with the honeypot filled, without storing it', async () => {
		const res = await post({ ...VALID, website: 'http://spam.example' });

		expect(await leadCount()).toBe(0);
	});

	it('answers 201 so a bot cannot tell which field caught it', async () => {
		const trapped = await post({ ...VALID, website: 'http://spam.example' });
		const genuine = await post(VALID);

		expect(trapped.status).toBe(genuine.status);
		await expect(trapped.json()).resolves.toEqual(await genuine.json());
	});

	it('stores a submission when the honeypot is present but empty', async () => {
		const res = await post({ ...VALID, website: '' });

		expect(res.status).toBe(201);
		expect(await leadCount()).toBe(1);
	});
});

describe('validation', () => {
	it.each([
		['a missing name', { ...VALID, name: '' }, 'Please tell us your name.'],
		['a whitespace-only name', { ...VALID, name: '   ' }, 'Please tell us your name.'],
		['a malformed email', { ...VALID, email: 'notanemail' }, 'That email address does not look right.'],
		['an email with no domain dot', { ...VALID, email: 'a@b' }, 'That email address does not look right.'],
		['an aws_scale outside the list', { ...VALID, aws_scale: 'lots' }, 'Please pick one of the listed options.'],
	])('rejects %s and stores nothing', async (_label, fields, message) => {
		const res = await post(fields);

		expect(res.status).toBe(422);
		await expect(res.json()).resolves.toEqual({ error: message });
		expect(await leadCount()).toBe(0);
	});

	it('allows an unstated aws_scale', async () => {
		const res = await post({ ...VALID, aws_scale: '' });

		expect(res.status).toBe(201);
		expect(await leadCount()).toBe(1);
	});
});

describe('routing', () => {
	it.each(['GET', 'PUT', 'DELETE'])('refuses %s', async (method) => {
		const res = await post(VALID, method);

		expect(res.status).toBe(405);
		expect(await leadCount()).toBe(0);
	});

	it('404s an unknown path rather than falling through to the handler', async () => {
		const request = new Request('https://teemops.com/api/nope', { method: 'POST' });
		const ctx = createExecutionContext();
		const res = await worker.fetch(request, env, ctx);
		await waitOnExecutionContext(ctx);

		expect(res.status).toBe(404);
	});
});
