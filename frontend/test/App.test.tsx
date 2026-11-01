import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { App } from '../src/App';
import type { Client, EditorData, NodeTypeInfo } from '../src/types';

vi.stubGlobal('ResizeObserver', class { observe() {} unobserve() {} disconnect() {} });

const param = (name: string, kind: string, extra: Partial<NodeTypeInfo['params'][0]> = {}) => ({
  name, kind, required: true, options: [], label: name.toUpperCase(), optionlabels: {}, ...extra,
}) as NodeTypeInfo['params'][0];

const nodetypes: NodeTypeInfo[] = [
  { type: 'trigger.manual', label: 'Started by hand', description: '', category: 'trigger', branches: [], params: [] },
  { type: 'trigger.event', label: 'On event', description: '', category: 'trigger', branches: [], params: [param('eventname', 'event')] },
  {
    type: 'logic.condition', label: 'Condition', description: '', category: 'logic', branches: ['true', 'false'],
    params: [param('left', 'text'), param('operator', 'select', { options: ['eq', 'gt'], optionlabels: { eq: 'is equal to', gt: 'is greater than' } })],
  },
  {
    type: 'moodle.call_function', label: 'Call a function', description: '', category: 'moodle', branches: [],
    params: [param('function', 'function'), param('args', 'json', { required: false })],
  },
  { type: 'action.log', label: 'Write to log', description: 'Logs it', category: 'action', branches: [], params: [param('message', 'textarea')] },
];

const strings: Record<string, string> = {
  save: 'Save', saved: 'Saved', runnow: 'Run now', runqueued: 'Run queued', enabled: 'Enabled', workflowname: 'Workflow name',
  palette: 'Add a node', nodesettings: 'Node settings', selectnode: 'Select a node', deletenode: 'Delete node', problems: 'Problems',
  saving: 'Saving...', savefailed: 'Could not save', 'branch:true': 'Yes', 'branch:false': 'No', choose: 'Choose...',
  workflowruns: 'Run history', invalidjson: 'Not valid JSON yet', 'cat:trigger': 'Triggers', 'cat:logic': 'Logic', 'cat:action': 'Actions', 'cat:moodle': 'Moodle',
};

const data = (overrides: Partial<EditorData> = {}): EditorData => ({
  id: 0, name: '', enabled: false, graph: { nodes: [], edges: [] }, nodetypes, events: ['\\core\\event\\user_created'], functions: ['core_webservice_get_site_info'], strings,
  ...overrides,
});

const fakeClient = (loaded: EditorData = data()) => ({
  load: vi.fn().mockResolvedValue(loaded),
  save: vi.fn().mockResolvedValue({ id: 7, enabled: true, errors: [] }),
  run: vi.fn().mockResolvedValue(true),
}) satisfies Client;

const renderApp = async (client: Client = fakeClient(), workflowid = 0) => {
  render(<App client={client} workflowid={workflowid} />);
  await screen.findByLabelText('Workflow name');
  return client;
};

describe('App', () => {
  it('loads the workflow and shows its name', async () => {
    const client = fakeClient(data({ id: 3, name: 'Welcome', enabled: true }));
    await renderApp(client, 3);
    expect(client.load).toHaveBeenCalledWith(3);
    expect(screen.getByLabelText('Workflow name')).toHaveValue('Welcome');
    expect(screen.getByLabelText('Enabled')).toBeChecked();
  });

  it('shows a load failure', async () => {
    const client = { ...fakeClient(), load: vi.fn().mockRejectedValue(new Error('Access denied')) };
    render(<App client={client} workflowid={1} />);
    expect(await screen.findByRole('alert')).toHaveTextContent('Access denied');
  });

  it('groups the palette by category', async () => {
    await renderApp();
    const palette = screen.getByRole('region', { name: 'Add a node' });
    expect(within(palette).getByRole('heading', { name: 'Triggers' })).toBeInTheDocument();
    expect(within(palette).getByRole('heading', { name: 'Logic' })).toBeInTheDocument();
    expect(within(palette).getByRole('button', { name: 'Write to log' })).toHaveAttribute('title', 'Logs it');
  });

  it('adds a node from the palette and selects it', async () => {
    const user = userEvent.setup();
    await renderApp();
    await user.click(screen.getByTestId('palette-action.log'));
    expect(screen.getByTestId('node-action_log_1')).toHaveTextContent('Write to log');
    expect(screen.getByLabelText('MESSAGE')).toBeInTheDocument();
  });

  it('edits parameters and saves them in the graph', async () => {
    const user = userEvent.setup();
    const client = await renderApp();
    await user.type(screen.getByLabelText('Workflow name'), 'Greeter');
    await user.click(screen.getByTestId('palette-trigger.manual'));
    await user.click(screen.getByTestId('palette-action.log'));
    await user.type(screen.getByLabelText('MESSAGE'), 'Hello');
    await user.click(screen.getByLabelText('Enabled'));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await screen.findByText('Saved');
    const request = (client.save as ReturnType<typeof vi.fn>).mock.calls[0][0];
    expect(request).toMatchObject({ id: 0, name: 'Greeter', enabled: true });
    expect(request.graph.nodes.map((n: { type: string }) => n.type)).toEqual(['trigger.manual', 'action.log']);
    expect(request.graph.nodes[1].data.params).toEqual({ message: 'Hello' });
  });

  it('uses the new id after the first save and runs the workflow', async () => {
    const user = userEvent.setup();
    const client = await renderApp();
    const run = screen.getByRole('button', { name: 'Run now' });
    expect(run).toBeDisabled();
    await user.click(screen.getByRole('button', { name: 'Save' }));
    await screen.findByText('Saved');
    expect(run).toBeEnabled();
    expect(window.location.search).toBe('?id=7');
    await user.click(run);
    expect(client.run).toHaveBeenCalledWith(7);
    expect(await screen.findByText('Run queued')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Save' }));
    await waitFor(() => expect((client.save as ReturnType<typeof vi.fn>).mock.calls[1][0].id).toBe(7));
  });

  it('shows the problems the server returns and turns enabled off', async () => {
    const user = userEvent.setup();
    const client = fakeClient(data({ enabled: true }));
    client.save.mockResolvedValue({ id: 7, enabled: false, errors: [{ code: 'notrigger', message: 'Add a trigger.', nodeids: [] }] });
    await renderApp(client);
    await user.click(screen.getByRole('button', { name: 'Save' }));
    expect(await screen.findByText('Add a trigger.')).toBeInTheDocument();
    expect(screen.getByLabelText('Enabled')).not.toBeChecked();
  });

  it('shows a save failure', async () => {
    const user = userEvent.setup();
    const client = fakeClient();
    client.save.mockRejectedValue(new Error('Network down'));
    await renderApp(client);
    await user.click(screen.getByRole('button', { name: 'Save' }));
    expect(await screen.findByText('Could not save: Network down')).toBeInTheDocument();
  });

  it('shows select options with their labels and event suggestions', async () => {
    const user = userEvent.setup();
    await renderApp();
    await user.click(screen.getByTestId('palette-logic.condition'));
    const select = screen.getByLabelText('OPERATOR');
    expect(within(select).getAllByRole('option').map((o) => o.textContent)).toEqual(['Choose...', 'is equal to', 'is greater than']);
    await user.selectOptions(select, 'gt');
    expect(select).toHaveValue('gt');

    await user.click(screen.getByTestId('palette-trigger.event'));
    const event = screen.getByLabelText('EVENTNAME');
    expect(event).toHaveAttribute('list', 'tool_autoflow-events');
    expect(document.querySelector('#tool_autoflow-events option')).toHaveAttribute('value', '\\core\\event\\user_created');
  });

  it('labels branch handles and gives triggers no input handle', async () => {
    const user = userEvent.setup();
    await renderApp();
    await user.click(screen.getByTestId('palette-trigger.manual'));
    await user.click(screen.getByTestId('palette-logic.condition'));
    const condition = screen.getByTestId('node-logic_condition_1');
    expect(within(condition).getByText('Yes')).toBeInTheDocument();
    expect(within(condition).getByText('No')).toBeInTheDocument();
    expect(condition.querySelectorAll('.react-flow__handle-top')).toHaveLength(1);
    expect(screen.getByTestId('node-trigger_manual_1').querySelectorAll('.react-flow__handle-top')).toHaveLength(0);
  });

  it('deletes the selected node', async () => {
    const user = userEvent.setup();
    await renderApp();
    await user.click(screen.getByTestId('palette-action.log'));
    await user.click(screen.getByRole('button', { name: 'Delete node' }));
    expect(screen.queryByTestId('node-action_log_1')).not.toBeInTheDocument();
    expect(screen.getByText('Select a node')).toBeInTheDocument();
  });

  it('selects a node when it is clicked', async () => {
    const graph = {
      nodes: [
        { id: 'a', type: 'action.log', data: { params: { message: 'first' } }, position: { x: 0, y: 0 } },
        { id: 'b', type: 'action.log', data: { params: { message: 'second' } }, position: { x: 0, y: 200 } },
      ],
      edges: [],
    };
    await renderApp(fakeClient(data({ id: 2, graph })), 2);
    expect(screen.getByText('Select a node')).toBeInTheDocument();
    // A plain click: the mousedown userEvent sends starts d3-drag, which needs a real browser window.
    fireEvent.click(screen.getByTestId('node-a'));
    expect(screen.getByLabelText('MESSAGE')).toHaveValue('first');
  });

  it('lists problems found in the editor before saving', async () => {
    const user = userEvent.setup();
    await renderApp();
    await user.click(screen.getByTestId('palette-action.log'));
    expect(screen.getByRole('region', { name: 'Problems' })).toHaveTextContent('notrigger');
  });

  const validGraph = {
    nodes: [
      { id: 't', type: 'trigger.manual', data: { params: {} }, position: { x: 0, y: 0 } },
      { id: 'if', type: 'logic.condition', data: { params: { left: 'a' } }, position: { x: 0, y: 150 } },
      { id: 'a', type: 'action.log', data: { params: { message: 'one' } }, position: { x: 0, y: 300 } },
      { id: 'b', type: 'action.log', data: { params: { message: 'two' } }, position: { x: 200, y: 300 } },
    ],
    edges: [
      { id: 't-if', source: 't', target: 'if' },
      { id: 'if-true-a', source: 'if', target: 'a', sourceHandle: 'true' },
      { id: 'if-false-b', source: 'if', target: 'b', sourceHandle: 'false' },
    ],
  };
  const savedGraph = (client: Client, call = 0) => (client.save as ReturnType<typeof vi.fn>).mock.calls[call][0].graph;

  it('shows no problems for a valid workflow', async () => {
    await renderApp(fakeClient(data({ id: 1, graph: validGraph })), 1);
    expect(screen.queryByRole('region', { name: 'Problems' })).not.toBeInTheDocument();
  });

  it('gives plain nodes one output and branching nodes one per branch', async () => {
    await renderApp(fakeClient(data({ id: 1, graph: validGraph })), 1);
    expect(screen.getByTestId('node-a').querySelectorAll('.react-flow__handle-bottom')).toHaveLength(1);
    expect(screen.getByTestId('node-a').querySelectorAll('.react-flow__handle-top')).toHaveLength(1);
    const branches = screen.getByTestId('node-if').querySelectorAll('.react-flow__handle-bottom');
    expect([...branches].map((h) => h.getAttribute('data-handleid'))).toEqual(['true', 'false']);
    // A node shows what its settings say; one with no settings shows its type.
    expect(screen.getByTestId('node-a')).toHaveTextContent('one');
    expect(screen.getByTestId('node-a').querySelector('.tool_autoflow-node-type')).toHaveAttribute('title', 'one');
    expect(screen.getByTestId('node-t')).toHaveTextContent('trigger.manual');
  });

  it('marks the selected node and moves the selection', async () => {
    await renderApp(fakeClient(data({ id: 1, graph: validGraph })), 1);
    fireEvent.click(screen.getByTestId('node-a'));
    expect(screen.getByTestId('node-a')).toHaveClass('tool_autoflow-node is-selected', { exact: true });
    expect(screen.getByTestId('node-b')).toHaveClass('tool_autoflow-node', { exact: true });
    fireEvent.click(screen.getByTestId('node-b'));
    expect(screen.getByTestId('node-a')).not.toHaveClass('is-selected');
    expect(screen.getByLabelText('MESSAGE')).toHaveValue('two');
  });

  it('clears the selection when the empty canvas is clicked', async () => {
    await renderApp(fakeClient(data({ id: 1, graph: validGraph })), 1);
    fireEvent.click(screen.getByTestId('node-a'));
    fireEvent.click(document.querySelector('.react-flow__pane') as Element);
    expect(screen.getByText('Select a node')).toBeInTheDocument();
  });

  it('marks the nodes a server problem names', async () => {
    const user = userEvent.setup();
    const client = fakeClient(data({ id: 1, graph: validGraph }));
    client.save.mockResolvedValue({ id: 1, enabled: false, errors: [{ code: 'unknowntype', message: 'Not installed.', nodeids: ['b'] }] });
    await renderApp(client, 1);
    await user.click(screen.getByRole('button', { name: 'Save' }));
    await screen.findByText('Not installed.');
    expect(screen.getByTestId('node-b')).toHaveClass('has-problem');
    expect(screen.getByTestId('node-a')).not.toHaveClass('has-problem');
  });

  it('edits only the selected node and keeps text, textarea and select values', async () => {
    const user = userEvent.setup();
    const client = await renderApp(fakeClient(data({ id: 1, graph: validGraph })), 1);
    fireEvent.click(screen.getByTestId('node-a'));
    expect(screen.getByLabelText('MESSAGE').tagName).toBe('TEXTAREA');
    await user.type(screen.getByLabelText('MESSAGE'), '!');
    fireEvent.click(screen.getByTestId('node-if'));
    expect(screen.getByLabelText('LEFT').tagName).toBe('INPUT');
    expect(screen.getByLabelText('LEFT')).toHaveValue('a');
    expect(screen.getByLabelText('LEFT')).not.toHaveAttribute('list');
    await user.type(screen.getByLabelText('LEFT'), 'b');
    await user.click(screen.getByRole('button', { name: 'Save' }));
    await screen.findByText('Saved');
    const params = Object.fromEntries(savedGraph(client).nodes.map((n: { id: string; data: { params: unknown } }) => [n.id, n.data.params]));
    expect(params).toEqual({ t: {}, if: { left: 'ab' }, a: { message: 'one!' }, b: { message: 'two' } });
  });

  it('deletes a node together with its links', async () => {
    const user = userEvent.setup();
    const client = await renderApp(fakeClient(data({ id: 1, graph: validGraph })), 1);
    fireEvent.click(screen.getByTestId('node-if'));
    await user.click(screen.getByRole('button', { name: 'Delete node' }));
    await user.click(screen.getByRole('button', { name: 'Save' }));
    await screen.findByText('Saved');
    expect(savedGraph(client).nodes.map((n: { id: string }) => n.id)).toEqual(['t', 'a', 'b']);
    expect(savedGraph(client).edges).toEqual([]);
  });

  it('keeps links that do not touch the deleted node', async () => {
    const user = userEvent.setup();
    const client = await renderApp(fakeClient(data({ id: 1, graph: validGraph })), 1);
    fireEvent.click(screen.getByTestId('node-b'));
    await user.click(screen.getByRole('button', { name: 'Delete node' }));
    await user.click(screen.getByRole('button', { name: 'Save' }));
    await screen.findByText('Saved');
    expect(savedGraph(client).edges.map((e: { id: string }) => e.id)).toEqual(['t-if', 'if-true-a']);
  });

  it('says it is saving until the server answers', async () => {
    const user = userEvent.setup();
    let finish: (v: unknown) => void = () => undefined;
    const client = fakeClient();
    client.save.mockReturnValue(new Promise((resolve) => { finish = resolve; }));
    await renderApp(client);
    await user.click(screen.getByRole('button', { name: 'Save' }));
    expect(screen.getByRole('status')).toHaveTextContent('Saving...');
    await act(async () => finish({ id: 3, enabled: false, errors: [] }));
    expect(screen.getByRole('status')).toHaveTextContent('Saved');
  });

  it('does not change the address when saving an existing workflow', async () => {
    const user = userEvent.setup();
    window.history.replaceState(null, '', '?id=1');
    const client = fakeClient(data({ id: 1, graph: validGraph }));
    client.save.mockResolvedValue({ id: 1, enabled: true, errors: [] });
    await renderApp(client, 1);
    window.history.replaceState(null, '', '?id=1&x=y');
    await user.click(screen.getByRole('button', { name: 'Save' }));
    await screen.findByText('Saved');
    expect(window.location.search).toBe('?id=1&x=y');
  });

  it('stores function arguments as an object once the text is valid JSON', async () => {
    const user = userEvent.setup();
    const client = await renderApp();
    await user.click(screen.getByTestId('palette-moodle.call_function'));
    expect(screen.getByLabelText('FUNCTION')).toHaveAttribute('list', 'tool_autoflow-functions');
    expect(screen.getByLabelText('FUNCTION')).toBeRequired();
    expect(screen.getByLabelText('ARGS')).not.toBeRequired();
    expect(document.querySelector('label[for="tool_autoflow-param-function"]')).toHaveClass('is-required');
    expect(document.querySelector('label[for="tool_autoflow-param-args"]')).not.toHaveClass('is-required');
    expect(document.querySelector('#tool_autoflow-functions option')).toHaveAttribute('value', 'core_webservice_get_site_info');
    await user.type(screen.getByLabelText('FUNCTION'), 'core_user_get_users_by_field');

    const args = screen.getByLabelText('ARGS');
    expect(args.tagName).toBe('TEXTAREA');
    // Set the text directly: userEvent reads [ and { as key commands.
    fireEvent.change(args, { target: { value: '{"field": "id"' } });
    expect(screen.getByText('Not valid JSON yet')).toBeInTheDocument();
    fireEvent.change(args, { target: { value: '{"field": "id", "values": ["{{trigger.userid}}"]}' } });
    expect(screen.queryByText('Not valid JSON yet')).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Save' }));
    await screen.findByText('Saved');
    const params = (client.save as ReturnType<typeof vi.fn>).mock.calls[0][0].graph.nodes[0].data.params;
    expect(params).toEqual({ function: 'core_user_get_users_by_field', args: { field: 'id', values: ['{{trigger.userid}}'] } });
  });

  it('shows saved JSON arguments formatted, and keeps text that is not an object', async () => {
    const user = userEvent.setup();
    const graph = { nodes: [{ id: 'c', type: 'moodle.call_function', data: { params: { args: { a: 1 } } }, position: { x: 0, y: 0 } }], edges: [] };
    const client = await renderApp(fakeClient(data({ id: 1, graph })), 1);
    fireEvent.click(screen.getByTestId('node-c'));
    const args = screen.getByLabelText('ARGS');
    expect(args).toHaveValue('{\n  "a": 1\n}');
    fireEvent.change(args, { target: { value: '[1]' } });
    expect(screen.getByText('Not valid JSON yet')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Save' }));
    await screen.findByText('Saved');
    expect((client.save as ReturnType<typeof vi.fn>).mock.calls[0][0].graph.nodes[0].data.params.args).toBe('[1]');
  });
});
