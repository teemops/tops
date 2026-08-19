import { cloudflareTest } from '@cloudflare/vitest-pool-workers';
import { defineConfig } from 'vitest/config';

// @cloudflare/vitest-pool-workers 0.20 dropped the "/config" subpath and its
// defineWorkersConfig / defineWorkersProject helpers. On vitest 4 the old
// test.poolOptions.workers object is passed to the cloudflareTest() plugin
// instead — that plugin is also what provides the "cloudflare:test" module.
export default defineConfig({
	plugins: [
		cloudflareTest({
			wrangler: { configPath: './wrangler.jsonc' },
			miniflare: {
				// Tests assert on row counts, so they must not share a database.
				isolatedStorage: true,
			},
		}),
	],
});
