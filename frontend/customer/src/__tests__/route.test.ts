import { describe, expect, it } from 'vitest';

import { parseRoute, tablePath } from '../lib/route';

/** docs/06-SAAS.md §7 — domen strategiyasi. */
describe('route parser', () => {
  it('parses the slug form', () => {
    expect(parseRoute('/r/osiyo/t/abc123')).toEqual({ slug: 'osiyo', nfcToken: 'abc123' });
  });

  it('parses the short form written on the NFC tag', () => {
    expect(parseRoute('/t/abc123')).toEqual({ slug: null, nfcToken: 'abc123' });
  });

  it('tolerates a trailing slash', () => {
    expect(parseRoute('/r/osiyo/t/abc123/')).toEqual({ slug: 'osiyo', nfcToken: 'abc123' });
  });

  it('rejects anything else', () => {
    expect(parseRoute('/')).toBeNull();
    expect(parseRoute('/t')).toBeNull();
    expect(parseRoute('/r/osiyo')).toBeNull();
    expect(parseRoute('/r/osiyo/x/abc')).toBeNull();
  });

  it('builds the api path back, keeping the slug when present', () => {
    expect(tablePath({ slug: 'osiyo', nfcToken: 'abc' }, '/menu')).toBe('/r/osiyo/t/abc/menu');
    expect(tablePath({ slug: null, nfcToken: 'abc' })).toBe('/t/abc');
  });
});
