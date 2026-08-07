/**
 * teemops.com — static pages plus one endpoint.
 *
 * Static files in public/ are served by the assets binding before this Worker
 * runs, so the only thing here is the design-partner signup handler.
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

async function handleSignup(request: Request, env: Env): Promise<Response> {
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

	return json({ ok: true }, 201);
}

export default {
	async fetch(request: Request, env: Env): Promise<Response> {
		const url = new URL(request.url);

		if (url.pathname === '/api/design-partner') {
			if (request.method !== 'POST') {
				return json({ error: 'Method not allowed.' }, 405);
			}
			return handleSignup(request, env);
		}

		return new Response('Not found', { status: 404 });
	},
} satisfies ExportedHandler<Env>;
