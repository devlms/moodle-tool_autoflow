import type { Connection, Edge, Node } from '@xyflow/react';
import type { Graph, GraphEdge } from './graph';
import type { NodeTypeInfo } from './types';

/** Data on every React Flow node: the workflow node type and its parameters. */
export type FlowNodeData = { nodetype: string; params: Record<string, unknown> };
export type FlowNode = Node<FlowNodeData, 'autoflow'>;

/** Saved graph to React Flow nodes and edges. */
export function toFlow(graph: Graph): { nodes: FlowNode[]; edges: Edge[] } {
  return {
    nodes: graph.nodes.map((n) => ({
      id: n.id,
      type: 'autoflow' as const,
      position: n.position ?? { x: 0, y: 0 },
      data: { nodetype: n.type, params: (n.data.params as Record<string, unknown>) ?? {} },
    })),
    edges: graph.edges.map((e) => ({ ...e })),
  };
}

/** React Flow nodes and edges back to the saved graph shape. */
export function fromFlow(nodes: FlowNode[], edges: Edge[]): Graph {
  return {
    nodes: nodes.map((n) => ({ id: n.id, type: n.data.nodetype, data: { params: n.data.params }, position: n.position })),
    edges: edges.map((e) => {
      const edge: GraphEdge = { id: e.id, source: e.source, target: e.target };
      if (e.sourceHandle) {
        edge.sourceHandle = e.sourceHandle;
      }
      return edge;
    }),
  };
}

/** A new node of a type, with an id no other node has, placed below the last one. */
export function newNode(type: string, existingIds: string[], count: number): FlowNode {
  const base = type.replace(/\./g, '_');
  let n = 1;
  while (existingIds.includes(`${base}_${n}`)) {
    n++;
  }
  return { id: `${base}_${n}`, type: 'autoflow', position: { x: 120, y: count * 110 + 40 }, data: { nodetype: type, params: {} } };
}

/** An edge for a connection the user drew, keeping the branch it leaves from. */
export function connectionToEdge(c: Connection): Edge {
  const edge: Edge = {
    id: c.sourceHandle ? `${c.source}-${c.sourceHandle}-${c.target}` : `${c.source}-${c.target}`,
    source: c.source,
    target: c.target,
  };
  if (c.sourceHandle) {
    edge.sourceHandle = c.sourceHandle;
  }
  return edge;
}

/** Longest summary shown on a node, in characters. */
const SUMMARY_LENGTH = 48;

/**
 * One line saying what a node will do: its filled settings in order, with option labels,
 * leaving out JSON settings. Empty when nothing is filled in yet.
 */
export function nodeSummary(info: NodeTypeInfo | undefined, params: Record<string, unknown>): string {
  const parts = (info?.params ?? [])
    .filter((p) => p.kind !== 'json')
    .map((p) => {
      const value = params[p.name];
      const text = typeof value === 'string' || typeof value === 'number' ? String(value).trim() : '';
      return p.optionlabels[text] ?? text;
    })
    .filter((text) => text !== '');
  const summary = parts.join(' ');
  return summary.length > SUMMARY_LENGTH ? `${summary.slice(0, SUMMARY_LENGTH - 1)}…` : summary;
}
