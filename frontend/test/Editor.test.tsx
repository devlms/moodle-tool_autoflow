import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Editor } from '../src/Editor';

// React Flow measures the DOM; jsdom has no layout, so give it the APIs it calls.
vi.stubGlobal('ResizeObserver', class { observe() {} unobserve() {} disconnect() {} });

const graph = {
  nodes: [
    { id: 't', type: 'trigger.manual', data: { label: 'Run now' }, position: { x: 0, y: 0 } },
    { id: 'a', type: 'action.log', data: { label: 'Write to log' }, position: { x: 0, y: 120 } },
  ],
  edges: [{ id: 't-a', source: 't', target: 'a' }],
};

describe('Editor', () => {
  it('shows a card for every node in the graph', () => {
    render(<Editor graph={graph} />);
    expect(screen.getByText('Run now')).toBeInTheDocument();
    expect(screen.getByText('Write to log')).toBeInTheDocument();
  });

  it('falls back to the node type when a node has no label', () => {
    render(<Editor graph={{ nodes: [{ id: 'x', type: 'trigger.schedule', data: {}, position: { x: 0, y: 0 } }], edges: [] }} />);
    expect(screen.getByText('trigger.schedule')).toBeInTheDocument();
  });

  it('lists validation problems under the canvas', () => {
    render(<Editor graph={{ nodes: [{ id: 'a', type: 'action.log', data: {}, position: { x: 0, y: 0 } }], edges: [] }} />);
    expect(screen.getByRole('alert')).toHaveTextContent('notrigger');
  });
});
