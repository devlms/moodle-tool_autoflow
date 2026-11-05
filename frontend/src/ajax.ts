import type { Client, EditorData, SaveRequest, SaveResult, UserSearchResult } from './types';

declare global {
  // Moodle puts its config on window.M.
  // eslint-disable-next-line no-var
  var M: { cfg: { wwwroot: string; sesskey: string } };
}

interface ServiceReply {
  error: boolean;
  data?: unknown;
  exception?: { message: string };
}

/** Calls one web service function the way core/ajax does: a POST to lib/ajax/service.php. */
export async function callService<T>(methodname: string, args: Record<string, unknown>): Promise<T> {
  const { wwwroot, sesskey } = globalThis.M.cfg;
  const response = await fetch(`${wwwroot}/lib/ajax/service.php?sesskey=${sesskey}&info=${methodname}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify([{ index: 0, methodname, args }]),
  });
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }
  const replies: unknown = await response.json();
  if (!Array.isArray(replies)) {
    throw new Error(`Unexpected reply from ${methodname}`);
  }
  const reply = replies[0] as ServiceReply;
  if (reply.error) {
    throw new Error(reply.exception?.message);
  }
  return reply.data as T;
}

interface RawEditorData extends Omit<EditorData, 'graph' | 'nodetypes' | 'events' | 'functions' | 'strings'> {
  graph: string;
  nodetypes: string;
  events: string;
  functions: string;
  strings: string;
}

/** The real client, talking to Moodle. */
export const moodleClient: Client = {
  async load(workflowid) {
    const raw = await callService<RawEditorData>('tool_autoflow_get_editor', { workflowid });
    return {
      ...raw,
      graph: JSON.parse(raw.graph),
      nodetypes: JSON.parse(raw.nodetypes),
      events: JSON.parse(raw.events),
      functions: JSON.parse(raw.functions),
      strings: JSON.parse(raw.strings),
    };
  },
  save(request: SaveRequest) {
    return callService<SaveResult>('tool_autoflow_save_workflow', { ...request, graph: JSON.stringify(request.graph) });
  },
  async run(workflowid) {
    return (await callService<{ queued: boolean }>('tool_autoflow_run_workflow', { id: workflowid })).queued;
  },
  searchUsers(query) {
    return callService<UserSearchResult>('tool_autoflow_search_users', { query });
  },
};
