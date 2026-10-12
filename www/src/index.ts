/**
 * teemops.com — static pages plus one endpoint.
 *
 * Static files in public/ are served by the assets binding before this Worker
 * runs, so the only thing here is the demo-request handler. The /api/design-partner
 * path and design_partner_leads table keep their original names; renaming the
 * table would mean migrating live data for no visible benefit.
 */

const MAX_BODY_BYTES = 8 * 1024;

const LIMITS = {
	name: 100,
	email: 254, // RFC 5321 maximum
	company: 100,
	aws_scale: 20,
	notes: 2000,
} as const;

const AWS_SCALES = new Set(['1', '2-5', '6-20', '20+', '']);

/** Must match the hidden input's name in public/index.html. */
const HONEYPOT_FIELD = 'website';

interface LeadSubmission {
	name: string;
	email: string;
	company: string;
	aws_scale: string;
	notes: string;
}

function json(body: unknown, status: number): Response {
	return new Response(JSON.stringify(body), {
		status,
		headers: { 'content-type': 'application/json; charset=utf-8' },
	});
}

/**
 * Email validation is deliberately loose. The only thing that matters is that a
 * reply can be sent, and the authoritative test of that is sending one — a
 * strict pattern here would reject valid addresses and cost real leads.
 */
function looksLikeEmail(value: string): boolean {
	return /^[^\s@]+@[^\s@.]+\.[^\s@]+$/.test(value);
}

function readField(form: FormData, key: string, limit: number): string {
	const raw = form.get(key);
	return typeof raw === 'string' ? raw.trim().slice(0, limit) : '';
}

function parseSubmission(form: FormData): { lead: LeadSubmission } | { error: string } {
	const lead: LeadSubmission = {
		name: readField(form, 'name', LIMITS.name),
		email: readField(form, 'email', LIMITS.email),
		company: readField(form, 'company', LIMITS.company),
		aws_scale: readField(form, 'aws_scale', LIMITS.aws_scale),
		notes: readField(form, 'notes', LIMITS.notes),
	};

	if (!lead.name) return { error: 'Please tell us your name.' };
	if (!looksLikeEmail(lead.email)) return { error: 'That email address does not look right.' };
	if (!AWS_SCALES.has(lead.aws_scale)) return { error: 'Please pick one of the listed options.' };

	return { lead };
}

/** Leads are emailed from here; notify.teemops.com is onboarded to Email Sending. */
const NOTIFY_FROM = { email: 'leads@notify.teemops.com', name: 'teemops.com' };
/** Must match the EMAIL binding's destination_address in wrangler.jsonc, or the send is refused. */
const NOTIFY_TO = 'help@teemops.com';

/**
 * Tell a human a lead arrived. The binding's destination_address pins the
 * recipient, so a bug here cannot email anyone else.
 * Runs after the row is stored and never throws: the database is the record,
 * and a failed email must not turn a captured lead into an error.
 */
async function notifyLead(env: Env, lead: LeadSubmission): Promise<void> {
	const text = [
		`Name:      ${lead.name}`,
		`Email:     ${lead.email}`,
		`Company:   ${lead.company || '—'}`,
		`AWS scale: ${lead.aws_scale || 'unstated'} accounts`,
		'',
		lead.notes || '(no notes)',
		'',
		'Reply to this email to reach them directly. All leads: `npm run leads` in www/.',
	].join('\n');

	try {
		await env.EMAIL.send({
			from: NOTIFY_FROM,
			to: NOTIFY_TO,
			replyTo: { email: lead.email, name: lead.name },
			subject: `Demo request: ${lead.name}${lead.company ? ` (${lead.company})` : ''}`,
			text,
		});
	} catch (error) {
		console.error(
			JSON.stringify({
				event: 'lead_notify_failed',
				code: (error as { code?: string }).code,
				message: error instanceof Error ? error.message : String(error),
			})
		);
	}
}

async function handleSignup(request: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
	const contentLength = Number(request.headers.get('content-length') ?? '0');
	if (contentLength > MAX_BODY_BYTES) {
		return json({ error: 'That message is too long.' }, 413);
	}

	let form: FormData;
	try {
		form = await request.formData();
	} catch {
		return json({ error: 'We could not read that submission.' }, 400);
	}

	// Honeypot. The field is hidden from people and left empty by them; bots that
	// fill every input give themselves away. Answer 201 rather than an error, so a
	// bot gets no signal telling it which field to skip next time.
	if (readField(form, HONEYPOT_FIELD, 200) !== '') {
		console.warn(JSON.stringify({ event: 'lead_rejected_honeypot' }));
		return json({ ok: true }, 201);
	}

	const parsed = parseSubmission(form);
	if ('error' in parsed) {
		return json({ error: parsed.error }, 422);
	}

	const { lead } = parsed;

	try {
		await env.LEADS_DB.prepare(
			`INSERT INTO design_partner_leads (name, email, company, aws_scale, notes)
			 VALUES (?, ?, ?, ?, ?)`
		)
			.bind(lead.name, lead.email, lead.company, lead.aws_scale, lead.notes)
			.run();
	} catch (error) {
		// Never surface the database error to the visitor, but do not pretend it
		// worked either — a lead that silently vanishes is the one failure this
		// endpoint exists to prevent.
		console.error(
			JSON.stringify({
				event: 'lead_insert_failed',
				message: error instanceof Error ? error.message : String(error),
			})
		);
		return json(
			{ error: 'Something broke on our end. Please email help@teemops.com instead.' },
			500
		);
	}

	console.log(JSON.stringify({ event: 'lead_captured', scale: lead.aws_scale || 'unstated' }));
	ctx.waitUntil(notifyLead(env, lead));

	return json({ ok: true }, 201);
}

export default {
	async fetch(request: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
		const url = new URL(request.url);

		if (url.pathname === '/api/design-partner') {
			if (request.method !== 'POST') {
				return json({ error: 'Method not allowed.' }, 405);
			}
			return handleSignup(request, env, ctx);
		}

		return new Response('Not found', { status: 404 });
	},
} satisfies ExportedHandler<Env>;
