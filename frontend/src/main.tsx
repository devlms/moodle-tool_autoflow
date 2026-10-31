import { createRoot } from 'react-dom/client';
import '@xyflow/react/dist/style.css';
import './editor.css';
import { App } from './App';
import { moodleClient } from './ajax';

/** Mounts the editor on every [data-tool-autoflow-editor] element that edit.php prints. */
function boot() {
  document.querySelectorAll<HTMLElement>('[data-tool-autoflow-editor]').forEach((element) => {
    createRoot(element).render(<App client={moodleClient} workflowid={Number(element.dataset.workflowId ?? 0)} />);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
