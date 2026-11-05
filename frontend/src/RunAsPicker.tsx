import { useEffect, useId, useRef, useState } from 'react';
import type { FoundUser, RunAs, UserSearch } from './types';

/** Wait this long after the last key press before searching. */
export const SEARCH_DELAY = 300;
/** Shortest text worth searching for. */
export const MIN_QUERY = 2;

export interface RunAsPickerProps {
  search: UserSearch;
  value: RunAs;
  onChange: (value: RunAs) => void;
  strings: Record<string, string>;
}

/**
 * Picks the user a workflow runs as: a combobox that searches users by name or email.
 * The text shows the chosen user; leaving the field without choosing puts it back.
 */
export function RunAsPicker({ search, value, onChange, strings }: RunAsPickerProps) {
  const id = useId();
  const listid = `${id}-list`;
  const [text, setText] = useState(value.name);
  const [open, setOpen] = useState(false);
  const [users, setUsers] = useState<FoundUser[]>([]);
  const [overflow, setOverflow] = useState(false);
  const [searched, setSearched] = useState(false);
  const [active, setActive] = useState(-1);
  // Only the reply to the newest search may change the list.
  const latest = useRef(0);

  useEffect(() => setText(value.name), [value]);

  useEffect(() => {
    const query = text.trim();
    if (!open || query.length < MIN_QUERY) {
      return undefined;
    }
    const timer = setTimeout(() => {
      const request = ++latest.current;
      search(query).then(
        (result) => {
          if (request === latest.current) {
            setUsers(result.users);
            setOverflow(result.overflow);
            setSearched(true);
            setActive(result.users.length > 0 ? 0 : -1);
          }
        },
        () => {
          if (request === latest.current) {
            setUsers([]);
            setOverflow(false);
            setSearched(true);
          }
        },
      );
    }, SEARCH_DELAY);
    return () => clearTimeout(timer);
  }, [text, open, search]);

  const close = () => {
    setOpen(false);
    setUsers([]);
    setSearched(false);
    setActive(-1);
    setText(value.name);
  };
  const choose = (user: FoundUser) => {
    onChange({ id: user.id, name: user.fullname });
    setOpen(false);
    setUsers([]);
    setSearched(false);
    setActive(-1);
    setText(user.fullname);
  };
  const showList = open && searched;

  return (
    <div className="tool_autoflow-runas-picker">
      <label htmlFor={id} className="tool_autoflow-runas-label">{strings.runsas}</label>
      <div className="tool_autoflow-combobox">
        <input id={id} type="text" className="form-control" role="combobox" autoComplete="off"
          aria-expanded={showList} aria-controls={listid} aria-autocomplete="list"
          aria-activedescendant={showList && active >= 0 ? `${listid}-${active}` : undefined}
          placeholder={strings.searchusers} value={text}
          onFocus={(e) => e.target.select()}
          onChange={(e) => {
            setText(e.target.value);
            setOpen(true);
            if (e.target.value.trim().length < MIN_QUERY) {
              setUsers([]);
              setSearched(false);
            }
          }}
          onBlur={close}
          onKeyDown={(e) => {
            if (e.key === 'ArrowDown' && users.length > 0) {
              e.preventDefault();
              setActive((a) => (a + 1) % users.length);
            } else if (e.key === 'ArrowUp' && users.length > 0) {
              e.preventDefault();
              setActive((a) => (a <= 0 ? users.length - 1 : a - 1));
            } else if (e.key === 'Enter' && showList && active >= 0) {
              e.preventDefault();
              choose(users[active]);
            } else if (e.key === 'Escape') {
              close();
            }
          }} />
        <ul id={listid} role="listbox" aria-label={strings.runsas} className="tool_autoflow-options" hidden={!showList}>
          {users.map((user, index) => (
            <li key={user.id} id={`${listid}-${index}`} role="option" aria-selected={index === active}
              className={index === active ? 'is-active' : undefined}
              onMouseDown={(e) => {
                // Keep focus in the field so blur does not close the list before the click lands.
                e.preventDefault();
                choose(user);
              }}>
              <span className="tool_autoflow-option-name">{user.fullname}</span>
              {user.identity && <span className="tool_autoflow-option-identity">{user.identity}</span>}
            </li>
          ))}
          {showList && users.length === 0 && <li className="tool_autoflow-options-note" role="presentation">{strings.nousersfound}</li>}
          {showList && overflow && <li className="tool_autoflow-options-note" role="presentation">{strings.moreusers}</li>}
        </ul>
      </div>
    </div>
  );
}
