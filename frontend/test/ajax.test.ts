import { afterEach, describe, expect, it, vi } from 'vitest';
import { callService, moodleClient } from '../src/ajax';

const respond = (body: unknown, ok = true) =>
  vi.fn().mockResolvedValue({ ok, status: ok ? 200 : 500, json: () => Promise.resolve(body) });

describe('callService', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('posts one request to service.php with the sesskey and returns its data', async () => {
    const fetch = respond([{ error: false, data: { id: 3 } }]);
    vi.stubGlobal('fetch', fetch);
    vi.stubGlobal('M', { cfg: { wwwroot: 'https://lms.test', sesskey: 'abc123' } });

    await expect(callService('tool_autoflow_get_editor', { workflowid: 3 })).resolves.toEqual({ id: 3 });
    expect(fetch).toHaveBeenCalledWith(
      'https://lms.test/lib/ajax/service.php?sesskey=abc123&info=tool_autoflow_get_editor',
      expect.objectContaining({ method: 'POST', credentials: 'same-origin' }),
    );
    const body = JSON.parse(fetch.mock.calls[0][1].body);
    expect(body).toEqual([{ index: 0, methodname: 'tool_autoflow_get_editor', args: { workflowid: 3 } }]);
    expect(fetch.mock.calls[0][1].headers).toEqual({ 'Content-Type': 'application/json' });
  });

  it('throws the Moodle error message', async () => {
    vi.stubGlobal('fetch', respond([{ error: true, exception: { message: 'Access denied' } }]));
    vi.stubGlobal('M', { cfg: { wwwroot: '', sesskey: 'x' } });
    await expect(callService('x', {})).rejects.toThrow('Access denied');
  });

  it('throws on an HTTP error', async () => {
    vi.stubGlobal('fetch', respond(null, false));
    vi.stubGlobal('M', { cfg: { wwwroot: '', sesskey: 'x' } });
    await expect(callService('x', {})).rejects.toThrow('HTTP 500');
  });

  it('throws when the reply is not a list', async () => {
    vi.stubGlobal('fetch', respond({ error: 'invalidsesskey' }));
    vi.stubGlobal('M', { cfg: { wwwroot: '', sesskey: 'x' } });
    await expect(callService('x', {})).rejects.toThrow('Unexpected reply');
  });
});

describe('moodleClient', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('decodes the JSON fields of get_editor', async () => {
    vi.stubGlobal('M', { cfg: { wwwroot: '', sesskey: 'x' } });
    vi.stubGlobal(
      'fetch',
      respond([
        {
          error: false,
          data: { id: 0, name: '', enabled: false, graph: '{"nodes":[],"edges":[]}', nodetypes: '[]', events: '["\\\\a"]', strings: '{"save":"Save"}' },
        },
      ]),
    );
    await expect(moodleClient.load(0)).resolves.toEqual({
      id: 0,
      name: '',
      enabled: false,
      graph: { nodes: [], edges: [] },
      nodetypes: [],
      events: ['\\a'],
      strings: { save: 'Save' },
    });
    expect(JSON.parse((globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0][1].body)[0]).toEqual({
      index: 0, methodname: 'tool_autoflow_get_editor', args: { workflowid: 0 } });
  });

  it('sends the graph as JSON when saving and queues runs', async () => {
    vi.stubGlobal('M', { cfg: { wwwroot: '', sesskey: 'x' } });
    const fetch = respond([{ error: false, data: { id: 5, enabled: true, errors: [] } }]);
    vi.stubGlobal('fetch', fetch);
    await moodleClient.save({ id: 0, name: 'N', enabled: true, graph: { nodes: [], edges: [] } });
    expect(JSON.parse(fetch.mock.calls[0][1].body)[0]).toEqual({ index: 0, methodname: 'tool_autoflow_save_workflow',
      args: { id: 0, name: 'N', enabled: true, graph: '{"nodes":[],"edges":[]}' } });

    fetch.mockResolvedValue({ ok: true, json: () => Promise.resolve([{ error: false, data: { queued: true } }]) });
    await expect(moodleClient.run(5)).resolves.toBe(true);
    expect(JSON.parse(fetch.mock.calls[1][1].body)[0]).toEqual({ index: 0, methodname: 'tool_autoflow_run_workflow', args: { id: 5 } });
  });
});
