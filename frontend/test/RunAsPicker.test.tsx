import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { RunAsPicker, SEARCH_DELAY } from '../src/RunAsPicker';
import type { RunAs, UserSearchResult } from '../src/types';

const strings = {
  runsas: 'Runs as', searchusers: 'Search by name or email', nousersfound: 'No users match.',
  moreusers: 'More users match. Keep typing.',
};

const ines = { id: 4, fullname: 'Ines Moreau', identity: 'ines@example.edu' };
const omar = { id: 5, fullname: 'Omar Haddad', identity: '' };

const setup = (reply: UserSearchResult = { users: [ines, omar], overflow: false }, value: RunAs = { id: 2, name: 'Admin User' }) => {
  const search = vi.fn().mockResolvedValue(reply);
  const onChange = vi.fn();
  const view = render(<RunAsPicker search={search} value={value} onChange={onChange} strings={strings} />);
  const field = screen.getByRole('combobox', { name: 'Runs as' });
  return { search, onChange, field, view };
};

/** Type into the field and let the search delay pass. */
const typeAndWait = async (field: HTMLElement, text: string) => {
  fireEvent.focus(field);
  fireEvent.change(field, { target: { value: text } });
  await act(async () => {
    vi.advanceTimersByTime(SEARCH_DELAY);
  });
};

describe('RunAsPicker', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('shows the current user and a closed list', () => {
    const { field } = setup();
    expect(field).toHaveValue('Admin User');
    expect(field).toHaveAttribute('aria-expanded', 'false');
    expect(field).toHaveAttribute('placeholder', 'Search by name or email');
    expect(screen.getByRole('listbox', { hidden: true })).not.toBeVisible();
  });

  it('waits for the typing to stop before searching', async () => {
    const { field, search } = setup();
    fireEvent.change(field, { target: { value: 'in' } });
    await act(async () => {
      vi.advanceTimersByTime(SEARCH_DELAY - 1);
    });
    expect(search).not.toHaveBeenCalled();
    fireEvent.change(field, { target: { value: 'ine' } });
    await act(async () => {
      vi.advanceTimersByTime(SEARCH_DELAY);
    });
    expect(search).toHaveBeenCalledTimes(1);
    expect(search).toHaveBeenCalledWith('ine');
  });

  it('does not search for one character', async () => {
    const { field, search } = setup();
    await typeAndWait(field, ' i ');
    expect(search).not.toHaveBeenCalled();
    expect(field).toHaveAttribute('aria-expanded', 'false');
  });

  it('lists matches with their identity and picks one by click', async () => {
    const { field, onChange } = setup();
    await typeAndWait(field, 'mo');
    expect(field).toHaveAttribute('aria-expanded', 'true');
    const options = screen.getAllByRole('option');
    expect(options).toHaveLength(2);
    expect(options[0]).toHaveTextContent('Ines Moreau');
    expect(options[0]).toHaveTextContent('ines@example.edu');
    expect(options[0]).toHaveAttribute('aria-selected', 'true');
    expect(field).toHaveAttribute('aria-activedescendant', options[0].id);
    fireEvent.mouseDown(options[1]);
    expect(onChange).toHaveBeenCalledWith({ id: 5, name: 'Omar Haddad' });
    expect(field).toHaveValue('Omar Haddad');
    expect(field).toHaveAttribute('aria-expanded', 'false');
  });

  it('moves through the list with the arrow keys and picks with Enter', async () => {
    const { field, onChange } = setup();
    await typeAndWait(field, 'mo');
    fireEvent.keyDown(field, { key: 'ArrowDown' });
    expect(screen.getAllByRole('option')[1]).toHaveAttribute('aria-selected', 'true');
    expect(screen.getAllByRole('option')[1]).toHaveClass('is-active');
    fireEvent.keyDown(field, { key: 'ArrowDown' });
    expect(screen.getAllByRole('option')[0]).toHaveAttribute('aria-selected', 'true');
    fireEvent.keyDown(field, { key: 'ArrowUp' });
    expect(screen.getAllByRole('option')[1]).toHaveAttribute('aria-selected', 'true');
    fireEvent.keyDown(field, { key: 'ArrowUp' });
    expect(screen.getAllByRole('option')[0]).toHaveAttribute('aria-selected', 'true');
    fireEvent.keyDown(field, { key: 'Enter' });
    expect(onChange).toHaveBeenCalledWith({ id: 4, name: 'Ines Moreau' });
  });

  it('keeps Enter for the form when nothing is listed', () => {
    const { field, onChange } = setup();
    const event = fireEvent.keyDown(field, { key: 'Enter' });
    expect(event).toBe(true);
    expect(onChange).not.toHaveBeenCalled();
  });

  it('puts the chosen user back on Escape and on leaving the field', async () => {
    const { field, onChange } = setup();
    await typeAndWait(field, 'mo');
    fireEvent.keyDown(field, { key: 'Escape' });
    expect(field).toHaveValue('Admin User');
    expect(field).toHaveAttribute('aria-expanded', 'false');
    await typeAndWait(field, 'om');
    fireEvent.blur(field);
    expect(field).toHaveValue('Admin User');
    expect(onChange).not.toHaveBeenCalled();
  });

  it('says when nobody matches and when there are more', async () => {
    const { field, view } = setup({ users: [], overflow: false });
    await typeAndWait(field, 'zz');
    expect(screen.getByText('No users match.')).toBeVisible();
    expect(field).not.toHaveAttribute('aria-activedescendant');
    fireEvent.keyDown(field, { key: 'ArrowDown' });
    fireEvent.keyDown(field, { key: 'Enter' });
    expect(screen.queryByText('More users match. Keep typing.')).not.toBeInTheDocument();
    view.unmount();

    const many = setup({ users: [ines], overflow: true });
    await typeAndWait(many.field, 'in');
    expect(screen.getByText('More users match. Keep typing.')).toBeVisible();
  });

  it('shows an empty list when the search fails', async () => {
    const { field, search } = setup();
    search.mockRejectedValue(new Error('No access'));
    await typeAndWait(field, 'mo');
    expect(screen.getByText('No users match.')).toBeVisible();
    expect(screen.queryAllByRole('option')).toHaveLength(0);
  });

  it('ignores a slow reply to an older search', async () => {
    const { field, search } = setup();
    let first: (r: UserSearchResult) => void = () => undefined;
    search.mockImplementationOnce(() => new Promise((resolve) => { first = resolve; }));
    search.mockResolvedValueOnce({ users: [omar], overflow: false });
    await typeAndWait(field, 'in');
    await typeAndWait(field, 'om');
    await act(async () => first({ users: [ines], overflow: false }));
    const options = screen.getAllByRole('option');
    expect(options).toHaveLength(1);
    expect(options[0]).toHaveTextContent('Omar Haddad');
  });

  it('ignores a slow failure of an older search', async () => {
    const { field, search } = setup();
    let fail: (e: Error) => void = () => undefined;
    search.mockImplementationOnce(() => new Promise((_, reject) => { fail = reject; }));
    search.mockResolvedValueOnce({ users: [omar], overflow: false });
    await typeAndWait(field, 'in');
    await typeAndWait(field, 'om');
    await act(async () => fail(new Error('late')));
    expect(screen.getAllByRole('option')).toHaveLength(1);
  });

  it('clears old matches when the text gets too short', async () => {
    const { field } = setup();
    await typeAndWait(field, 'mo');
    fireEvent.change(field, { target: { value: 'm' } });
    expect(screen.queryAllByRole('option')).toHaveLength(0);
    expect(field).toHaveAttribute('aria-expanded', 'false');
  });

  it('follows a new value from outside, such as after saving', () => {
    const { view, field } = setup();
    view.rerender(<RunAsPicker search={vi.fn()} value={{ id: 9, name: 'Sam Student' }} onChange={vi.fn()} strings={strings} />);
    expect(field).toHaveValue('Sam Student');
  });
});
