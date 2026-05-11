const STORAGE_KEY = 'todos-vanillajs';
const app = document.querySelector('[data-app]');
const newTodo = app?.querySelector('.new-todo');
const main = app?.querySelector('.main');
const toggleAll = app?.querySelector('.toggle-all');
const todoList = app?.querySelector('.todo-list');
const footer = app?.querySelector('.footer');
const todoCount = app?.querySelector('.todo-count');
const clearCompleted = app?.querySelector('.clear-completed');
const filters = app?.querySelectorAll('.filters a');

let todos = [];
let editingId = null;

const nextId = () => `${Date.now()}-${Math.random().toString(16).slice(2)}`;

const activeTodos = () => todos.filter((todo) => !todo.completed);
const completedTodos = () => todos.filter((todo) => todo.completed);
const currentRoute = () => (['#/active', '#/completed'].includes(window.location.hash) ? window.location.hash : '#/');

const visibleTodos = () => {
  if (currentRoute() === '#/active') {
    return activeTodos();
  }

  if (currentRoute() === '#/completed') {
    return completedTodos();
  }

  return todos;
};

const escapeHtml = (value) => value.replace(/[&<>"']/g, (char) => ({
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#039;',
})[char]);

const saveTodos = () => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(todos));
};

const loadTodos = () => {
  try {
    const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');

    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed
      .filter((todo) => typeof todo?.id === 'string' && typeof todo?.title === 'string')
      .map((todo) => ({
        id: todo.id,
        title: todo.title,
        completed: todo.completed === true,
      }));
  } catch {
    return [];
  }
};

const setTodos = (nextTodos) => {
  todos = nextTodos;
  saveTodos();
  render();
};

const render = () => {
  if (!(main instanceof HTMLElement)
    || !(footer instanceof HTMLElement)
    || !(toggleAll instanceof HTMLInputElement)
    || !(todoList instanceof HTMLUListElement)
    || !(todoCount instanceof HTMLElement)
    || !(clearCompleted instanceof HTMLButtonElement)) {
    return;
  }

  const activeCount = activeTodos().length;
  const completedCount = completedTodos().length;
  const hasTodos = todos.length > 0;
  const route = currentRoute();

  main.hidden = !hasTodos;
  footer.hidden = !hasTodos;
  toggleAll.checked = hasTodos && activeCount === 0;
  clearCompleted.hidden = completedCount === 0;
  todoCount.innerHTML = `<strong>${activeCount}</strong> ${activeCount === 1 ? 'item' : 'items'} left`;

  filters?.forEach((filter) => {
    filter.classList.toggle('selected', filter.getAttribute('href') === route);
  });

  todoList.innerHTML = visibleTodos().map((todo) => `
    <li data-id="${todo.id}" class="${[
      todo.completed ? 'completed' : '',
      editingId === todo.id ? 'editing' : '',
    ].filter(Boolean).join(' ')}">
      <div class="view">
        <input class="toggle" type="checkbox" ${todo.completed ? 'checked' : ''}>
        <label>${escapeHtml(todo.title)}</label>
        <button class="destroy" aria-label="Delete ${escapeHtml(todo.title)}"></button>
      </div>
      <input class="edit" value="${escapeHtml(todo.title)}">
    </li>
  `).join('');

  if (editingId !== null) {
    const editInput = todoList.querySelector(`li[data-id="${CSS.escape(editingId)}"] .edit`);

    if (editInput instanceof HTMLInputElement) {
      editInput.focus();
      editInput.selectionStart = editInput.value.length;
      editInput.selectionEnd = editInput.value.length;
    }
  }
};

const addTodo = (title) => {
  setTodos([...todos, { id: nextId(), title, completed: false }]);
};

const updateTodo = (id, changes) => {
  setTodos(todos.map((todo) => (todo.id === id ? { ...todo, ...changes } : todo)));
};

const removeTodo = (id) => {
  setTodos(todos.filter((todo) => todo.id !== id));
};

const startEditing = (id) => {
  editingId = id;
  render();
};

const stopEditing = () => {
  editingId = null;
  render();
};

const saveEdit = (id, value) => {
  const title = value.trim();
  editingId = null;

  if (title === '') {
    removeTodo(id);
    return;
  }

  updateTodo(id, { title });
};

if (newTodo instanceof HTMLInputElement) {
  newTodo.focus();
  newTodo.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') {
      return;
    }

    const title = newTodo.value.trim();
    if (title === '') {
      return;
    }

    addTodo(title);
    newTodo.value = '';
  });
}

if (toggleAll instanceof HTMLInputElement) {
  toggleAll.addEventListener('change', () => {
    const completed = toggleAll.checked;
    setTodos(todos.map((todo) => ({ ...todo, completed })));
  });
}

if (todoList instanceof HTMLUListElement) {
  todoList.addEventListener('change', (event) => {
    const target = event.target;
    const item = target instanceof HTMLElement ? target.closest('li[data-id]') : null;

    if (!(target instanceof HTMLInputElement) || !target.classList.contains('toggle') || item === null) {
      return;
    }

    updateTodo(item.dataset.id, { completed: target.checked });
  });

  todoList.addEventListener('dblclick', (event) => {
    const target = event.target;
    const item = target instanceof HTMLElement ? target.closest('li[data-id]') : null;

    if (!(target instanceof HTMLLabelElement) || item === null) {
      return;
    }

    startEditing(item.dataset.id);
  });

  todoList.addEventListener('click', (event) => {
    const target = event.target;
    const item = target instanceof HTMLElement ? target.closest('li[data-id]') : null;

    if (!(target instanceof HTMLButtonElement) || !target.classList.contains('destroy') || item === null) {
      return;
    }

    removeTodo(item.dataset.id);
  });

  todoList.addEventListener('keydown', (event) => {
    const target = event.target;
    const item = target instanceof HTMLElement ? target.closest('li[data-id]') : null;

    if (!(target instanceof HTMLInputElement) || !target.classList.contains('edit') || item === null) {
      return;
    }

    if (event.key === 'Enter') {
      target.dataset.committed = 'true';
      saveEdit(item.dataset.id, target.value);
    }

    if (event.key === 'Escape') {
      target.dataset.committed = 'true';
      stopEditing();
    }
  });

  todoList.addEventListener('focusout', (event) => {
    const target = event.target;
    const item = target instanceof HTMLElement ? target.closest('li[data-id]') : null;

    if (!(target instanceof HTMLInputElement)
      || !target.classList.contains('edit')
      || item === null
      || target.dataset.committed === 'true') {
      return;
    }

    saveEdit(item.dataset.id, target.value);
  });
}

if (clearCompleted instanceof HTMLButtonElement) {
  clearCompleted.addEventListener('click', () => {
    setTodos(activeTodos());
  });
}

window.addEventListener('hashchange', render);

todos = loadTodos();
render();

export { STORAGE_KEY };
