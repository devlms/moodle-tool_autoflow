import { describe, expect, it } from 'vitest';
import { validateGraph, type Graph } from '../src/graph';

const node = (id: string, type = 'action.log') => ({ id, type, data: {} });
const edge = (source: string, target: string, sourceHandle?: string) => ({
  id: `${source}-${target}`,
  source,
  target,
  ...(sourceHandle ? { sourceHandle } : {}),
});

const codes = (graph: Graph) => validateGraph(graph).map((e) => e.code);

describe('validateGraph', () => {
  it('accepts a trigger followed by a chain of actions', () => {
    const graph = {
      nodes: [node('t', 'trigger.manual'), node('a'), node('b')],
      edges: [edge('t', 'a'), edge('a', 'b')],
    };
    expect(validateGraph(graph)).toEqual([]);
  });

  it('requires a trigger', () => {
    expect(codes({ nodes: [node('a')], edges: [] })).toEqual(['notrigger']);
    expect(codes({ nodes: [], edges: [] })).toEqual(['notrigger']);
  });

  it('allows only one trigger', () => {
    const graph = { nodes: [node('t1', 'trigger.manual'), node('t2', 'trigger.schedule')], edges: [] };
    expect(validateGraph(graph)).toEqual([{ code: 'manytriggers', nodeIds: ['t1', 't2'] }]);
  });

  it('rejects duplicate node ids', () => {
    const graph = { nodes: [node('t', 'trigger.manual'), node('a'), node('a')], edges: [edge('t', 'a')] };
    expect(validateGraph(graph)).toContainEqual({ code: 'duplicateid', nodeIds: ['a'] });
  });

  it('rejects edges to or from missing nodes', () => {
    const graph = { nodes: [node('t', 'trigger.manual')], edges: [edge('t', 'ghost'), edge('ghost2', 't')] };
    expect(validateGraph(graph)).toContainEqual({ code: 'danglingedge', nodeIds: ['ghost', 'ghost2'] });
  });

  it('rejects cycles', () => {
    const graph = {
      nodes: [node('t', 'trigger.manual'), node('a'), node('b')],
      edges: [edge('t', 'a'), edge('a', 'b'), edge('b', 'a')],
    };
    expect(codes(graph)).toEqual(['cycle']);
  });

  it('rejects a node linked to itself', () => {
    const graph = { nodes: [node('t', 'trigger.manual'), node('a')], edges: [edge('t', 'a'), edge('a', 'a')] };
    expect(codes(graph)).toEqual(['cycle']);
  });

  it('reports nodes the trigger cannot reach', () => {
    const graph = {
      nodes: [node('t', 'trigger.manual'), node('a'), node('lonely'), node('island')],
      edges: [edge('t', 'a'), edge('lonely', 'island')],
    };
    expect(validateGraph(graph)).toEqual([{ code: 'unreachable', nodeIds: ['lonely', 'island'] }]);
  });

  it('accepts branches that join again', () => {
    const graph = {
      nodes: [node('t', 'trigger.manual'), node('if', 'logic.condition'), node('yes'), node('no'), node('end')],
      edges: [edge('t', 'if'), edge('if', 'yes', 'true'), edge('if', 'no', 'false'), edge('yes', 'end'), edge('no', 'end')],
    };
    expect(validateGraph(graph)).toEqual([]);
  });

  it('does not let an edge into the trigger count as a cycle on its own', () => {
    const graph = { nodes: [node('t', 'trigger.manual'), node('a')], edges: [edge('t', 'a'), edge('a', 't')] };
    expect(codes(graph)).toEqual(['cycle']);
  });
});

describe('validateGraph on large graphs', () => {
  // 30 diamonds in a row have 2^30 paths. Visiting each node once keeps this instant;
  // walking paths instead of nodes would never finish.
  it('handles long chains of branches that rejoin', () => {
    const nodes = [node('t', 'trigger.manual'), node('j0')];
    const edges = [edge('t', 'j0')];
    for (let i = 0; i < 30; i++) {
      nodes.push(node(`l${i}`), node(`r${i}`), node(`j${i + 1}`));
      edges.push(edge(`j${i}`, `l${i}`), edge(`j${i}`, `r${i}`), edge(`l${i}`, `j${i + 1}`), edge(`r${i}`, `j${i + 1}`));
    }
    // Both node orders: a search that revisits finished nodes only blows up when the deepest come first.
    for (const order of [nodes, [...nodes].reverse()]) {
      const started = Date.now();
      expect(validateGraph({ nodes: order, edges })).toEqual([]);
      expect(Date.now() - started).toBeLessThan(500);
    }
  });
});
