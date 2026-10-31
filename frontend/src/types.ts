import type { Graph } from './graph';

/** One parameter of a node type, as described by node_catalogue::describe(). */
export interface ParamInfo {
  name: string;
  kind: 'text' | 'textarea' | 'number' | 'select' | 'event' | 'user';
  required: boolean;
  options: string[];
  label: string;
  optionlabels: Record<string, string>;
}

/** One entry of the node palette. */
export interface NodeTypeInfo {
  type: string;
  label: string;
  description: string;
  category: string;
  branches: string[];
  params: ParamInfo[];
}

export interface EditorData {
  id: number;
  name: string;
  enabled: boolean;
  graph: Graph;
  nodetypes: NodeTypeInfo[];
  events: string[];
  strings: Record<string, string>;
}

export interface SaveRequest {
  id: number;
  name: string;
  enabled: boolean;
  graph: Graph;
}

export interface SaveProblem {
  code: string;
  message: string;
  nodeids: string[];
}

export interface SaveResult {
  id: number;
  enabled: boolean;
  errors: SaveProblem[];
}

/** What the editor needs from the server. Tests pass a fake. */
export interface Client {
  load(workflowid: number): Promise<EditorData>;
  save(request: SaveRequest): Promise<SaveResult>;
  run(workflowid: number): Promise<boolean>;
}
