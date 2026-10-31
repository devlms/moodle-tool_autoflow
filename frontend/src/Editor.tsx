import { Background, Controls, ReactFlow, type Edge, type Node } from '@xyflow/react';
import { useMemo } from 'react';
import { validateGraph, type Graph } from './graph';

export interface EditorProps {
  graph: Graph;
}

/** The workflow canvas. Labels come from node data, or the node type when there is none. */
export function Editor({ graph }: EditorProps) {
  const nodes: Node[] = useMemo(
    () =>
      graph.nodes.map((n) => ({
        id: n.id,
        position: n.position ?? { x: 0, y: 0 },
        data: { label: typeof n.data.label === 'string' ? n.data.label : n.type },
      })),
    [graph.nodes],
  );
  const edges: Edge[] = useMemo(
    () => graph.edges.map((e) => ({ id: e.id, source: e.source, target: e.target, sourceHandle: e.sourceHandle })),
    [graph.edges],
  );
  const errors = validateGraph(graph);

  return (
    <div className="tool_autoflow-editor">
      <div className="tool_autoflow-canvas" style={{ height: 480 }}>
        <ReactFlow nodes={nodes} edges={edges} fitView>
          <Background />
          <Controls />
        </ReactFlow>
      </div>
      {errors.length > 0 && (
        <ul role="alert" className="tool_autoflow-errors">
          {errors.map((e) => (
            <li key={e.code}>{e.code}</li>
          ))}
        </ul>
      )}
    </div>
  );
}
