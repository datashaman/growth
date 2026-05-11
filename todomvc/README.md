# TodoMVC

Standalone TodoMVC implementation area for the captured specification.

## Run

Open `index.html` directly in a browser, or serve this folder with any static file server:

```bash
cd todomvc
python3 -m http.server 5174
```

Then visit `http://localhost:5174`.

## Implementation Notes

- No build step or package install is required.
- The app uses the canonical TodoMVC section/class structure for comparability.
- Browser persistence uses `localStorage` key `todos-vanillajs`.
- Filtering uses the canonical hash routes: `#/`, `#/active`, and `#/completed`.
