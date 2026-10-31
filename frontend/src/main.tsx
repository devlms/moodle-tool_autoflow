import { createRoot } from 'react-dom/client';
import '@xyflow/react/dist/style.css';
import { Editor } from './Editor';
import type { Graph } from './graph';

/** Called by amd/src/editor.js. Returns a function that unmounts the editor. */
export function mount(element: HTMLElement, props: { graph: Graph }): () => void {
  const root = createRoot(element);
  root.render(<Editor graph={props.graph} />);
  return () => root.unmount();
}
