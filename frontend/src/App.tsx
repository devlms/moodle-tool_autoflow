import {
  Background,
  Controls,
  Handle,
  Position,
  ReactFlow,
  useEdgesState,
  useNodesState,
  type Connection,
  type Edge,
  type NodeProps,
} from '@xyflow/react';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { connectionToEdge, fromFlow, newNode, toFlow, type FlowNode } from './flow';
import { isTrigger, validateGraph } from './graph';
import type { Client, EditorData, NodeTypeInfo, ParamInfo, SaveProblem } from './types';

interface Labels {
  types: Map<string, NodeTypeInfo>;
  strings: Record<string, string>;
  marked: Set<string>;
}

const LabelsContext = createContext<Labels>({ types: new Map(), strings: {}, marked: new Set() });

/** A node on the canvas: its label, an input handle unless it is a trigger, and one output per branch. */
function NodeView({ id, data, selected }: NodeProps<FlowNode>) {
  const { types, strings, marked } = useContext(LabelsContext);
  const info = types.get(data.nodetype);
  const branches = info?.branches ?? [];
  const classes = ['tool_autoflow-node', selected ? 'is-selected' : '', marked.has(id) ? 'has-problem' : ''];
  return (
    <div data-testid={`node-${id}`} className={classes.filter(Boolean).join(' ')}>
      {!isTrigger(data.nodetype) && <Handle type="target" position={Position.Top} />}
      <div className="tool_autoflow-node-label">{info?.label ?? data.nodetype}</div>
      <div className="tool_autoflow-node-type">{data.nodetype}</div>
      {branches.length === 0 ? (
        <Handle type="source" position={Position.Bottom} />
      ) : (
        <div className="tool_autoflow-branches">
          {branches.map((b) => (
            <div key={b} className="tool_autoflow-branch">
              <span>{strings[`branch:${b}`] ?? b}</span>
              <Handle type="source" id={b} position={Position.Bottom} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

const nodeTypes = { autoflow: NodeView };

function ParamField({ param, value, onChange, strings }: {
  param: ParamInfo; value: unknown; onChange: (v: string) => void; strings: Record<string, string>;
}) {
  const id = `tool_autoflow-param-${param.name}`;
  const text = value === undefined || value === null ? '' : String(value);
  let input;
  if (param.kind === 'textarea') {
    input = <textarea id={id} className="form-control" rows={4} value={text} onChange={(e) => onChange(e.target.value)} />;
  } else if (param.kind === 'select') {
    input = (
      <select id={id} className="form-select" value={text} onChange={(e) => onChange(e.target.value)}>
        <option value="">{strings.choose}</option>
        {param.options.map((o) => (
          <option key={o} value={o}>{param.optionlabels[o] ?? o}</option>
        ))}
      </select>
    );
  } else {
    const list = param.kind === 'event' ? 'tool_autoflow-events' : undefined;
    input = <input id={id} className="form-control" list={list} value={text} onChange={(e) => onChange(e.target.value)} />;
  }
  return (
    <div className="mb-2">
      <label htmlFor={id} className="form-label">{param.label}</label>
      {input}
    </div>
  );
}

export interface AppProps {
  client: Client;
  workflowid: number;
}

/** The workflow editor: toolbar, palette, canvas, node settings and problems. */
export function App({ client, workflowid }: AppProps) {
  const [data, setData] = useState<EditorData | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [nodes, setNodes, onNodesChange] = useNodesState<FlowNode>([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState<Edge>([]);
  const [selected, setSelected] = useState<string | null>(null);
  const [id, setId] = useState(workflowid);
  const [name, setName] = useState('');
  const [enabled, setEnabled] = useState(false);
  const [status, setStatus] = useState('');
  const [problems, setProblems] = useState<SaveProblem[]>([]);

  useEffect(() => {
    client.load(workflowid).then(
      (loaded) => {
        const flow = toFlow(loaded.graph);
        setNodes(flow.nodes);
        setEdges(flow.edges);
        setName(loaded.name);
        setEnabled(loaded.enabled);
        setData(loaded);
      },
      (e: Error) => setLoadError(e.message),
    );
  }, [client, workflowid, setNodes, setEdges]);

  const types = useMemo(() => new Map((data?.nodetypes ?? []).map((t) => [t.type, t])), [data]);
  const marked = useMemo(() => new Set(problems.flatMap((p) => p.nodeids)), [problems]);
  const onConnect = useCallback((c: Connection) => setEdges((es) => [...es, connectionToEdge(c)]), [setEdges]);

  if (loadError !== null) {
    return <div role="alert" className="alert alert-danger">{loadError}</div>;
  }
  if (data === null) {
    return <div className="tool_autoflow-loading" aria-busy="true" />;
  }
  const strings = data.strings;
  const graph = fromFlow(nodes, edges);
  const clientProblems = validateGraph(graph);
  const current = nodes.find((n) => n.id === selected);
  const currentType = current ? types.get(current.data.nodetype) : undefined;
  const categories = [...new Set(data.nodetypes.map((t) => t.category))];

  const add = (type: string) => {
    const node = newNode(type, nodes.map((n) => n.id), nodes.length);
    setNodes((ns) => [...ns, node]);
    setSelected(node.id);
  };
  const remove = (nodeid: string) => {
    setNodes((ns) => ns.filter((n) => n.id !== nodeid));
    setEdges((es) => es.filter((e) => e.source !== nodeid && e.target !== nodeid));
    setSelected(null);
  };
  const setParam = (nodeid: string, key: string, value: string) =>
    setNodes((ns) => ns.map((n) => (n.id === nodeid ? { ...n, data: { ...n.data, params: { ...n.data.params, [key]: value } } } : n)));
  const save = async () => {
    setStatus(strings.saving);
    try {
      const result = await client.save({ id, name, enabled, graph });
      if (id === 0) {
        // Keep the new workflow on reload.
        window.history.replaceState(null, '', `?id=${result.id}`);
      }
      setId(result.id);
      setEnabled(result.enabled);
      setProblems(result.errors);
      setStatus(strings.saved);
    } catch (e) {
      setStatus(`${strings.savefailed}: ${(e as Error).message}`);
    }
  };
  const run = async () => {
    await client.run(id);
    setStatus(strings.runqueued);
  };

  return (
    <LabelsContext.Provider value={{ types, strings, marked }}>
      <div className="tool_autoflow-editor">
        <div className="tool_autoflow-toolbar">
          <label htmlFor="tool_autoflow-name" className="visually-hidden sr-only">{strings.workflowname}</label>
          <input id="tool_autoflow-name" className="form-control" placeholder={strings.workflowname} value={name}
            onChange={(e) => setName(e.target.value)} />
          <div className="form-check">
            <input id="tool_autoflow-enabled" type="checkbox" className="form-check-input" checked={enabled}
              onChange={(e) => setEnabled(e.target.checked)} />
            <label htmlFor="tool_autoflow-enabled" className="form-check-label">{strings.enabled}</label>
          </div>
          <button type="button" className="btn btn-primary" onClick={save}>{strings.save}</button>
          <button type="button" className="btn btn-secondary" onClick={run} disabled={id === 0}>{strings.runnow}</button>
          <span className="tool_autoflow-status" role="status">{status}</span>
        </div>
        <div className="tool_autoflow-body">
          <section className="tool_autoflow-palette" aria-label={strings.palette}>
            {categories.map((category) => (
              <div key={category}>
                <h4>{strings[`cat:${category}`] ?? category}</h4>
                {data.nodetypes.filter((t) => t.category === category).map((t) => (
                  <button key={t.type} type="button" className="btn btn-outline-secondary btn-sm" title={t.description}
                    data-testid={`palette-${t.type}`} onClick={() => add(t.type)}>{t.label}</button>
                ))}
              </div>
            ))}
          </section>
          <div className="tool_autoflow-canvas">
            <ReactFlow nodes={nodes.map((n) => ({ ...n, selected: n.id === selected }))} edges={edges} nodeTypes={nodeTypes}
              onNodesChange={onNodesChange} onEdgesChange={onEdgesChange} onConnect={onConnect}
              onNodeClick={(_, n) => setSelected(n.id)} onPaneClick={() => setSelected(null)} fitView
              fitViewOptions={{ maxZoom: 1 }}>
              <Background />
              <Controls />
            </ReactFlow>
          </div>
          <aside className="tool_autoflow-inspector" aria-label={strings.nodesettings}>
            {current && currentType ? (
              <>
                <h4>{currentType.label}</h4>
                {currentType.params.map((p) => (
                  <ParamField key={p.name} param={p} value={current.data.params[p.name]} strings={strings}
                    onChange={(v) => setParam(current.id, p.name, v)} />
                ))}
                <button type="button" className="btn btn-link text-danger" onClick={() => remove(current.id)}>{strings.deletenode}</button>
              </>
            ) : (
              <p>{strings.selectnode}</p>
            )}
          </aside>
        </div>
        {(clientProblems.length > 0 || problems.length > 0) && (
          <section className="tool_autoflow-problems" aria-label={strings.problems}>
            <h4>{strings.problems}</h4>
            <ul>
              {/* Stryker disable next-line StringLiteral: list keys never change what renders. */}
              {problems.map((p) => <li key={`s-${p.code}`}>{p.message}</li>)}
              {/* Stryker disable next-line StringLiteral: list keys never change what renders. */}
              {clientProblems.map((p) => <li key={`c-${p.code}`}>{strings[`error:${p.code}`] ?? p.code}</li>)}
            </ul>
          </section>
        )}
        <datalist id="tool_autoflow-events">
          {data.events.map((e) => <option key={e} value={e} />)}
        </datalist>
      </div>
    </LabelsContext.Provider>
  );
}
