import {
  Background,
  Controls,
  Handle,
  MarkerType,
  Position,
  ReactFlow,
  useEdgesState,
  useNodesState,
  type Connection,
  type Edge,
  type NodeProps,
} from '@xyflow/react';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { connectionToEdge, fromFlow, newNode, nodeSummary, toFlow, type FlowNode } from './flow';
import { isTrigger, validateGraph } from './graph';
import { RunAsPicker } from './RunAsPicker';
import type { Client, EditorData, NodeTypeInfo, ParamInfo, RunAs, SaveProblem } from './types';

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
  const classes = ['tool_autoflow-node', `is-${info?.category ?? 'unknown'}`, selected ? 'is-selected' : '',
    marked.has(id) ? 'has-problem' : ''];
  const summary = nodeSummary(info, data.params) || data.nodetype;
  return (
    <div data-testid={`node-${id}`} className={classes.filter(Boolean).join(' ')}>
      {!isTrigger(data.nodetype) && <Handle type="target" position={Position.Top} />}
      <div className="tool_autoflow-node-label">{info?.label ?? data.nodetype}</div>
      <div className="tool_autoflow-node-type" title={summary}>{summary}</div>
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

const isObject = (v: unknown): v is Record<string, unknown> => typeof v === 'object' && v !== null && !Array.isArray(v);

/**
 * Function arguments: a JSON object. It is stored as an object once the text parses, so
 * placeholders are filled value by value on the server. Until then the text is kept as is.
 */
function JsonField({ id, value, onChange, invalid, required }: {
  id: string; value: unknown; onChange: (v: unknown) => void; invalid: string; required: boolean;
}) {
  const [text, setText] = useState(isObject(value) ? JSON.stringify(value, null, 2) : String(value ?? ''));
  let parsed: unknown;
  try {
    parsed = JSON.parse(text);
  } catch {
    parsed = undefined;
  }
  const valid = text.trim() === '' || isObject(parsed);
  return (
    <>
      <textarea id={id} className="form-control font-monospace tool_autoflow-code" rows={6} value={text} required={required}
        onChange={(e) => {
          setText(e.target.value);
          let next: unknown = e.target.value;
          try {
            const candidate = JSON.parse(e.target.value);
            next = isObject(candidate) ? candidate : e.target.value;
          } catch {
            // Keep the text until it is a JSON object.
          }
          onChange(next);
        }} />
      {!valid && <div className="form-text text-danger">{invalid}</div>}
    </>
  );
}

function ParamField({ param, value, onChange, strings }: {
  param: ParamInfo; value: unknown; onChange: (v: unknown) => void; strings: Record<string, string>;
}) {
  const id = `tool_autoflow-param-${param.name}`;
  const text = value === undefined || value === null ? '' : String(value);
  let input;
  if (param.kind === 'json') {
    input = <JsonField id={id} value={value} onChange={onChange} invalid={strings.invalidjson} required={param.required} />;
  } else if (param.kind === 'textarea') {
    input = <textarea id={id} className="form-control" rows={4} value={text} required={param.required} onChange={(e) => onChange(e.target.value)} />;
  } else if (param.kind === 'select') {
    input = (
      <select id={id} className="form-select custom-select" value={text} required={param.required} onChange={(e) => onChange(e.target.value)}>
        <option value="">{strings.choose}</option>
        {param.options.map((o) => (
          <option key={o} value={o}>{param.optionlabels[o] ?? o}</option>
        ))}
      </select>
    );
  } else {
    const lists: Record<string, string> = { event: 'tool_autoflow-events', function: 'tool_autoflow-functions' };
    const list = lists[param.kind];
    input = <input id={id} className="form-control" list={list} value={text} required={param.required}
      onChange={(e) => onChange(e.target.value)} />;
  }
  return (
    <div className="mb-2">
      <label htmlFor={id} className={param.required ? 'form-label is-required' : 'form-label'}>{param.label}</label>
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
  const [runas, setRunas] = useState<RunAs>({ id: 0, name: '' });
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
        setRunas({ id: loaded.runasuserid, name: loaded.runasname });
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
  const setParam = (nodeid: string, key: string, value: unknown) =>
    setNodes((ns) => ns.map((n) => (n.id === nodeid ? { ...n, data: { ...n.data, params: { ...n.data.params, [key]: value } } } : n)));
  const save = async () => {
    setStatus(strings.saving);
    try {
      const runasuserid = data.canchooserunas ? runas.id : 0;
      const result = await client.save({ id, name, enabled, graph, runasuserid });
      if (id === 0) {
        // Keep the new workflow on reload.
        window.history.replaceState(null, '', `?id=${result.id}`);
      }
      setId(result.id);
      setEnabled(result.enabled);
      setProblems(result.errors);
      if (result.runasuserid !== undefined) {
        setRunas({ id: result.runasuserid, name: result.runasname ?? '' });
      }
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
          <div className="tool_autoflow-toolbar-main">
            <label htmlFor="tool_autoflow-name" className="tool_autoflow-sr">{strings.workflowname}</label>
            <input id="tool_autoflow-name" className="form-control tool_autoflow-name" placeholder={strings.workflowname} value={name}
              onChange={(e) => setName(e.target.value)} />
            <div className="tool_autoflow-enabled">
              <input id="tool_autoflow-enabled" type="checkbox" checked={enabled} onChange={(e) => setEnabled(e.target.checked)} />
              <label htmlFor="tool_autoflow-enabled">{strings.enabled}</label>
            </div>
            {data.canchooserunas ? (
              <RunAsPicker search={client.searchUsers} value={runas} onChange={setRunas} strings={strings} />
            ) : (
              <span className="tool_autoflow-runas" data-testid="runas">{strings.runsas} <strong>{runas.name}</strong></span>
            )}
          </div>
          <div className="tool_autoflow-toolbar-actions">
            <span className="tool_autoflow-status" role="status">{status}</span>
            <button type="button" className="btn btn-outline-secondary" onClick={run} disabled={id === 0}>{strings.runnow}</button>
            <button type="button" className="btn btn-primary" onClick={save}>{strings.save}</button>
          </div>
        </div>
        <div className="tool_autoflow-body">
          <section className="tool_autoflow-palette" aria-label={strings.palette}>
            {categories.map((category) => (
              <div key={category}>
                <h4>{strings[`cat:${category}`] ?? category}</h4>
                {data.nodetypes.filter((t) => t.category === category).map((t) => (
                  <button key={t.type} type="button" className={`tool_autoflow-palette-item is-${category}`} title={t.description}
                    data-testid={`palette-${t.type}`} onClick={() => add(t.type)}>{t.label}</button>
                ))}
              </div>
            ))}
          </section>
          <div className="tool_autoflow-canvas">
            <ReactFlow nodes={nodes.map((n) => ({ ...n, selected: n.id === selected }))} edges={edges} nodeTypes={nodeTypes}
              onNodesChange={onNodesChange} onEdgesChange={onEdgesChange} onConnect={onConnect}
              onNodeClick={(_, n) => setSelected(n.id)} onPaneClick={() => setSelected(null)} fitView
              defaultEdgeOptions={{ markerEnd: { type: MarkerType.ArrowClosed } }}
              fitViewOptions={{ maxZoom: 1, padding: 0.15 }}>
              <Background />
              <Controls position="top-right" />
            </ReactFlow>
            {nodes.length === 0 && <p className="tool_autoflow-canvas-empty">{strings.emptycanvas}</p>}
          </div>
          <aside className="tool_autoflow-inspector" aria-label={strings.nodesettings}>
            {current && currentType ? (
              <>
                <h4>{currentType.label}</h4>
                {currentType.description && <p className="tool_autoflow-inspector-help">{currentType.description}</p>}
                {currentType.params.map((p) => (
                  <ParamField key={`${current.id}:${p.name}`} param={p} value={current.data.params[p.name]} strings={strings}
                    onChange={(v) => setParam(current.id, p.name, v)} />
                ))}
                <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => remove(current.id)}>{strings.deletenode}</button>
              </>
            ) : (
              <p className="tool_autoflow-inspector-empty">{strings.selectnode}</p>
            )}
          </aside>
        </div>
        {(clientProblems.length > 0 || problems.length > 0) && (
          <section className="tool_autoflow-problems alert alert-danger" aria-label={strings.problems}>
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
        <datalist id="tool_autoflow-functions">
          {data.functions.map((f) => <option key={f} value={f} />)}
        </datalist>
      </div>
    </LabelsContext.Provider>
  );
}
