import { describe, expect, it } from 'vitest';
import { fromFlow, newNode, toFlow, connectionToEdge, nodeSummary } from '../src/flow';
import type { NodeTypeInfo } from '../src/types';

const graph = {
  nodes: [
    { id: 't', type: 'trigger.manual', data: { params: {} }, position: { x: 10, y: 20 } },
    { id: 'if', type: 'logic.condition', data: { params: { left: '{{trigger.x}}', operator: 'eq', right: '1' } }, position: { x: 10, y: 140 } },
  ],
  edges: [
    { id: 't-if', source: 't', target: 'if' },
    { id: 'if-t', source: 'if', target: 't', sourceHandle: 'true' },
  ],
};

describe('toFlow and fromFlow', () => {
  it('turns the saved graph into React Flow nodes and back without loss', () => {
    const flow = toFlow(graph);
    expect(flow.nodes[1]).toEqual({
      id: 'if',
      type: 'autoflow',
      position: { x: 10, y: 140 },
      data: { nodetype: 'logic.condition', params: { left: '{{trigger.x}}', operator: 'eq', right: '1' } },
    });
    expect(flow.edges[1]).toEqual({ id: 'if-t', source: 'if', target: 't', sourceHandle: 'true' });
    expect(flow.edges[0]).toEqual({ id: 't-if', source: 't', target: 'if' });
    expect(fromFlow(flow.nodes, flow.edges)).toEqual(graph);
  });

  it('fills in missing params and positions', () => {
    const flow = toFlow({ nodes: [{ id: 'a', type: 'action.log', data: {} }], edges: [] });
    expect(flow.nodes[0].position).toEqual({ x: 0, y: 0 });
    expect(flow.nodes[0].data.params).toEqual({});
  });

  it('drops a null handle when saving', () => {
    const saved = fromFlow([], [{ id: 'e', source: 'a', target: 'b', sourceHandle: null }]);
    expect(saved.edges).toEqual([{ id: 'e', source: 'a', target: 'b' }]);
  });
});

describe('newNode', () => {
  it('makes a unique id from the type and the existing ids', () => {
    expect(newNode('action.log', [], 0).id).toBe('action_log_1');
    expect(newNode('action.log', ['action_log_1', 'action_log_2'], 0).id).toBe('action_log_3');
  });

  it('stacks new nodes below each other with empty params', () => {
    const n = newNode('logic.set', [], 3);
    expect(n.position).toEqual({ x: 120, y: 3 * 110 + 40 });
    expect(n.data).toEqual({ nodetype: 'logic.set', params: {} });
    expect(n.type).toBe('autoflow');
  });
});

describe('connectionToEdge', () => {
  it('names the edge after its ends and keeps the branch handle', () => {
    expect(connectionToEdge({ source: 'if', target: 'a', sourceHandle: 'false', targetHandle: null })).toEqual({
      id: 'if-false-a',
      source: 'if',
      target: 'a',
      sourceHandle: 'false',
    });
    expect(connectionToEdge({ source: 't', target: 'a', sourceHandle: null, targetHandle: null })).toEqual({
      id: 't-a',
      source: 't',
      target: 'a',
    });
  });
});

describe('nodeSummary', () => {
  const http: NodeTypeInfo = {
    type: 'action.http', label: 'HTTP', description: '', category: 'action', branches: [],
    params: [
      { name: 'method', kind: 'select', required: true, options: ['get', 'post'], label: 'Method', optionlabels: { post: 'POST' } },
      { name: 'url', kind: 'text', required: true, options: [], label: 'URL', optionlabels: {} },
      { name: 'body', kind: 'json', required: false, options: [], label: 'Body', optionlabels: {} },
    ],
  };

  it('joins the filled settings in order, with option labels, skipping JSON', () => {
    expect(nodeSummary(http, { method: 'post', url: 'https://x.test/a', body: { a: 1 } })).toBe('POST https://x.test/a');
  });

  it('uses the raw value when an option has no label, and numbers as text', () => {
    expect(nodeSummary(http, { method: 'get', url: 5 })).toBe('get 5');
  });

  it('skips blank values', () => {
    expect(nodeSummary(http, { method: '', url: '  https://x.test ' })).toBe('https://x.test');
  });

  it('cuts long summaries', () => {
    const summary = nodeSummary(http, { url: 'https://example.com/' + 'a'.repeat(80) });
    expect(summary).toHaveLength(48);
    expect(summary.endsWith('…')).toBe(true);
    expect(nodeSummary(http, { url: 'b'.repeat(48) })).toBe('b'.repeat(48));
  });

  it('is empty without settings or a known type', () => {
    expect(nodeSummary(http, {})).toBe('');
    expect(nodeSummary(undefined, { url: 'x' })).toBe('');
  });
});
