// Graph shape shared with the PHP engine (classes/local/engine/graph.php). The same
// rules are checked on both sides: the editor for quick feedback, the server for trust.

export interface GraphNode {
  id: string;
  type: string;
  data: Record<string, unknown>;
  position?: { x: number; y: number };
}

export interface GraphEdge {
  id: string;
  source: string;
  target: string;
  sourceHandle?: string;
}

export interface Graph {
  nodes: GraphNode[];
  edges: GraphEdge[];
}

export type GraphErrorCode = 'notrigger' | 'manytriggers' | 'duplicateid' | 'danglingedge' | 'cycle' | 'unreachable';

export interface GraphError {
  code: GraphErrorCode;
  nodeIds?: string[];
}

export const isTrigger = (type: string): boolean => type.startsWith('trigger.');

/** Every problem that stops a graph from running. An empty list means it is valid. */
export function validateGraph(graph: Graph): GraphError[] {
  const errors: GraphError[] = [];
  const ids = new Set<string>();
  const duplicates = new Set<string>();
  for (const n of graph.nodes) {
    if (ids.has(n.id)) {
      duplicates.add(n.id);
    }
    ids.add(n.id);
  }

  const triggers = graph.nodes.filter((n) => isTrigger(n.type)).map((n) => n.id);
  if (triggers.length === 0) {
    return [{ code: 'notrigger' }];
  }
  if (triggers.length > 1) {
    errors.push({ code: 'manytriggers', nodeIds: triggers });
  }
  if (duplicates.size > 0) {
    errors.push({ code: 'duplicateid', nodeIds: [...duplicates] });
  }

  const dangling = new Set<string>();
  for (const e of graph.edges) {
    for (const end of [e.target, e.source]) {
      if (!ids.has(end)) {
        dangling.add(end);
      }
    }
  }
  if (dangling.size > 0) {
    errors.push({ code: 'danglingedge', nodeIds: [...dangling] });
  }
  if (errors.length > 0) {
    return errors;
  }

  // Every edge now joins two known nodes.
  const next = new Map<string, string[]>([...ids].map((id) => [id, []]));
  for (const e of graph.edges) {
    (next.get(e.source) as string[]).push(e.target);
  }

  if (hasCycle([...ids], next)) {
    return [{ code: 'cycle' }];
  }

  const reached = new Set<string>([triggers[0]]);
  const queue = [triggers[0]];
  while (queue.length > 0) {
    for (const target of next.get(queue.shift() as string) as string[]) {
      if (!reached.has(target)) {
        reached.add(target);
        queue.push(target);
      }
    }
  }
  const unreachable = [...ids].filter((id) => !reached.has(id));
  if (unreachable.length > 0) {
    return [{ code: 'unreachable', nodeIds: unreachable }];
  }
  return [];
}

/** Depth-first search with three colours: a grey node reached again means a cycle. */
function hasCycle(ids: string[], next: Map<string, string[]>): boolean {
  const state = new Map<string, 'grey' | 'black'>();
  const visit = (id: string): boolean => {
    state.set(id, 'grey');
    for (const target of next.get(id) as string[]) {
      const s = state.get(target);
      if (s === 'grey' || (s === undefined && visit(target))) {
        return true;
      }
    }
    state.set(id, 'black');
    return false;
  };
  // Stryker disable next-line ConditionalExpression: revisiting a finished node here costs one step and changes nothing.
  return ids.some((id) => state.get(id) === undefined && visit(id));
}
