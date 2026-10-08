[← Docs index](README.md)

# Frontend

---

## Koi build step nahi

Project me **npm / webpack / vite kuch nahi** hai. Saara CSS/JS `public/assets/` me direct
rehta hai aur browser wahi load karta hai.

**Iske rules:**

| ✅ Karo | ❌ Mat karo |
|---|---|
| `app-custom.css` me CSS likho | Theme ki `style.css` edit karo |
| Vanilla JS / jQuery use karo | Nayi npm library add karo |
| Blade me `@push('scripts')` | CDN se bhaari library load karo |
| Theme tokens (`--bs-*`) use karo | Hardcoded colours |

> Dashboard ka chart isliye **pure CSS** se bana hai — Chart.js ke liye ya CDN chahiye
> (offline break) ya build step (jo hai hi nahi).

---

## Layout structure

```
resources/views/layouts/app.blade.php
├── partials/nav-header.blade.php    ← logo (black bar)
├── partials/header.blade.php        ← hamburger + page name
├── partials/sidebar.blade.php       ← menu (config/navigation.php se)
├── <main class="content-body">
│   ├── partials/page-title.blade.php  ← breadcrumb
│   ├── flash messages (success / error)
│   └── @yield('content')
└── partials/footer.blade.php
```

**Har page ka skeleton:**

```blade
@extends('layouts.app')

@section('title', 'Finder — AI Client Finder')   {{-- browser tab --}}
@section('page-title', 'Finder')                 {{-- header bar --}}

@section('breadcrumb')
    <li class="breadcrumb-item active">Finder</li>
@endsection

@section('content')
    ...
@endsection

@push('scripts')
    <script>...</script>
@endpush
```

> **Note:** `page-title` partial se `<h1>` hata diya gaya hai (user request). Page ka naam
> ab header bar aur breadcrumb me dikhta hai. `@section('page-title')` abhi bhi chahiye —
> header bar use consume karta hai.

---

## `ajax-filters.js` — sabse important shared file

`public/assets/js/ajax-filters.js`

Ek hi file **saare list screens** chalati hai: Templates, Platforms, Categories, Addresses,
**Finder, Verifier, Bulks**.

### Contract

```html
<div class="card" data-ajax-root>

    <!-- Filter bar -->
    <input data-live-filter>                <!-- client-side: screen ki rows hide -->
    <input data-search-param="q">           <!-- server-side: debounced search -->
    <select class="select2" data-param="status">   <!-- server-side filter -->

    <!-- Ye wrapper AJAX swap me BACHTA hai (loader ka anchor) -->
    <div class="ajax-region">
        <!-- Ye node REPLACE hota hai -->
        <div class="ajax-content">
            ...rows (har row par data-search)...
            <select data-param="per_page">  <!-- delegation se handle -->
            <div class="pagination">...</div>  <!-- delegation se handle -->
        </div>
    </div>
</div>
```

### Kaam kaise karta hai

```
User filter badalta hai
   │
   ▼
URL banao: current URL + param    (page=1 reset)
   │
   ▼
fetch(url, { 'X-Requested-With': 'XMLHttpRequest' })
   │
   ▼
Server: $request->ajax() → sirf partial return karta hai
   │
   ▼
document me .ajax-content ko outerHTML se replace karo
   │
   ▼
history.pushState(url)   ← URL shareable, back button kaam karta hai
   │
   ▼
live filter dobara apply karo (nayi rows unfiltered aayi hain)
```

**In-flight abort:** naya request aaye to purana `AbortController` se cancel ho jata hai —
fast typing par race condition nahi hoti.

### Naya kya add kiya

| Feature | Kya karta hai | Kyun |
|---|---|---|
| **Pagination links** | AJAX se load hote hain | Pehle full page reload hota tha |
| **Per-page select** | Region ke andar, delegation se | Swap me replace hota hai, init-time binding nahi bachti |
| **`data-search-param`** | Debounced (400ms) server-side search | `data-live-filter` sirf screen ki rows hide karta hai |

**Delegation kyun zaroori hai:**

```js
// ❌ Ye kaam nahi karega
document.querySelector('.pagination a').addEventListener(...)
// pehle swap me ye node gayab ho jayega

// ✅ Ye kaam karega
region.addEventListener('click', function (e) {
    const link = e.target.closest('.pagination a');
    if (! link) return;
    e.preventDefault();
    load(link.href, true);
});
// region hamesha rehta hai, uske andar kuch bhi replace ho
```

**Init-time selects se region wale alag kyun:**

```js
const selects = Array.prototype.filter.call(
    root.querySelectorAll('select[data-param]'),
    (select) => ! region.contains(select),
);
```

Warna per-page select do baar bind hota — ek baar init par (Select2 ke saath) aur ek baar
delegation se — matlab **har change par do requests**.

### `data-live-filter` vs `data-search-param`

| | `data-live-filter` | `data-search-param` |
|---|---|---|
| Kahan chalta hai | Browser me | Server par |
| Kya karta hai | Rows ko `d-none` karta hai | Query me `WHERE` lagata hai |
| Scope | **Sirf current page** | **Poora dataset** |
| Request | Koi nahi | Debounced (400ms) |
| Kab use karo | Chhoti list (Platforms, Bulks) | Badi list (Finder, Verifier) |

> **Bada dataset ho to `data-live-filter` galat hai** — user page 1 par search karega aur
> match page 7 par hoga, to use lagega "kuch nahi mila".

---

## CSS components

`public/assets/css/app-custom.css` — naye modules ke liye ye components add kiye:

| Class | Kahan |
|---|---|
| `.stat-card` + `.stat-icon` + `.stat-value` | Dashboard, Verifier, Bulks ke stat cards |
| `.activity-chart` + `.activity-bar` | Dashboard chart |
| `.score-meter` | Confidence bars (Finder, Verifier, Bulks) |
| `.check-list` | Verifier ka check breakdown |
| `.result-panel` + `.result-row` | Finder / Verifier ke results |
| `.bulk-progress` | Progress bars |
| `.stat-chip` + `.chip-row` | Bulk detail ke breakdown chips |
| `.inline-loading` | "Searching…" spinner |
| `.cell-wrap`, `.mw-220` | Long URLs/emails wrap karne ke liye |

### Theme tokens — hardcoded colours nahi

```css
/* ✅ Aise */
.stat-icon.tint-primary { background: var(--bs-primary-bg-subtle); color: var(--bs-primary); }

/* ❌ Aise nahi */
.stat-icon.tint-primary { background: #e7f1ff; color: #0d6efd; }
```

Theme me switcher hai — colours badal sakte hain. Tokens use karne se naye components bhi
**automatically** saath me badal jaate hain.

---

## AJAX partial ke rules

Jo partial swap hota hai (`_results`, `_history`, `_table`, `_items`) uske teen rule hain:

### 1. Ek hi root element

```blade
<div class="ajax-content">
    ...sab kuch...
</div>
```

`wrap.outerHTML = html` chalta hai — agar do sibling roots honge to sirf pehla replace hoga.

### 2. Koi `<script>` nahi

Swap kiya hua HTML `innerHTML` se aata hai — usme `<script>` **execute nahi hota**.
Saara JS parent page me `@push('scripts')` me hona chahiye, delegation ke saath.

### 3. Controller dono serve kare

```php
if ($request->ajax()) {
    return view('finder._results', $data);   // sirf partial
}
return view('finder.index', $data);          // pura page
```

Ek hi `$data`, ek hi filter logic — AJAX aur full load ka result **hamesha same**.

---

## JS patterns

### XSS escape

Server ka koi bhi value DOM me jane se pehle:

```js
function esc(value) {
    const div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
}
```

Har jagah `esc(c.email)`, `esc(c.reason)` use hota hai. Blade `{{ }}` auto-escape karta hai,
par JS me manually karna padta hai.

### Route names hardcode nahi

```blade
const searchUrl = @json(route('finder.search'));
const statusUrl = @json(route('bulks.status', ['id' => '__ID__']));
// JS me: statusUrl.replace('__ID__', id)
```

URL hamesha `routes/web.php` ka owned rehta hai.

### Polling — apne aap band

```js
if (! data.running) window.clearInterval(timer);       // run khatam
if (++failures >= 3) window.clearInterval(timer);      // server down
if (! row.isConnected) window.clearInterval(timer);    // row replace ho gaya
document.addEventListener('visibilitychange', ...);    // tab hidden
```

Completed run ke numbers kabhi change nahi hote — polling waste hai.

### Loading / error / empty states

Har async action ke chaar states hote hain. Finder aur Verifier dono me:

```js
renderLoading('Scanning the website…');
renderResults(data);
renderState('bi-inbox', 'No addresses found', '...');
renderState('bi-exclamation-triangle', 'Search failed', message);
renderState('bi-wifi-off', 'Connection problem', '...');
```

---

## Responsive

Desktop design **deliberately untouched** hai. Saara responsive kaam ya to media query me
hai, ya aisa structural guard hai jo desktop par kuch nahi badalta.

### Breakpoints

| Width | Kya hota hai |
|---|---|
| ≤1200px | Page padding thoda kam |
| ≤992px | Card header ke actions apni line par; table ke secondary columns hide |
| ≤768px | **Sidebar drawer ban jata hai** (theme); filter controls stack; modal gutter kam |
| ≤576px | Filter controls full width; card header buttons full width; result rows stack; chart labels vertical |
| ≤480px | Padding/font aur tight; stat card icons chhote |

Bootstrap ke apne breakpoints hain, taaki `d-none d-md-table-cell` jaise utility classes
CSS ke saath step me rahein.

### Overflow guards (har width par)

Ye desktop par kuch nahi badalte, bas page ko sideways jaane se rokte hain:

```css
.content-body, .container-fluid, .card, .card-body,
.table-responsive, .result-panel, .stat-body { min-width: 0; }
```

> **`min-width: 0` kyun?** Flex/grid child ka default `min-width: auto` hota hai — matlab
> wo apne content se chhota hone se **mana** kar deta hai. Ek lambi URL ya wide table
> flex column me ho, to wahi poore page ko sideways push kar deta hai. `min-width: 0`
> lagane se wo shrink hota hai aur overflow inner scroller (`.table-responsive`) ko de
> deta hai — jahan wo hona chahiye.

Saath me: `overflow-wrap: anywhere` long strings par, `img { max-width: 100% }`,
`.pagination { flex-wrap: wrap }`.

### Tables — do-part strategy

1. **Scroll** — har table `.table-responsive` me hai, to wo apne container ke andar
   horizontally scroll karti hai. Page kabhi scroll nahi karta.
2. **Hide + carry** — secondary columns Bootstrap ke `d-none d-md-table-cell` se hide hote
   hain, **aur unki value ek visible cell me carry** ho jati hai:

```blade
<th class="d-none d-lg-table-cell">Category</th>
...
<td>
    <h6 class="cell-wrap">{{ $lead->company_name }}</h6>
    {{-- Category/Found lg ke neeche hidden hain - value yahan carry karo --}}
    <div class="d-lg-none fs-13 text-muted">
        @if($lead->category){{ $lead->category->name }} · @endif
        {{ $lead->created_at?->format('j M Y') }}
    </div>
</td>
```

> **Sirf hide karna galat hai** — user ko information chahiye, bas kam jagah me. Isliye
> har hidden column ki value kisi rehne wale cell me sub-text ban jati hai.
>
> ⚠️ `<th>` aur uske `<td>` par **same class** honi chahiye, warna columns shift ho jayenge.

**Live-updating cells ka dhyan:** Bulks index ka poller `[data-cell="successful"]` update
karta hai. Wo column mobile par hidden hai, isliye mobile summary line ke apne markers hain
(`data-cell="successful-sm"`) aur poller dono update karta hai — warna resize par purana
number dikh jata.

### Mobile sidebar drawer

Theme `<768px` par khud `data-sidebar-style="overlay"` set karta hai
(`deznav-init.js`), sidebar `left:-100%` se `left:0` slide hota hai, aur `.content-body`
`margin-left:0` par rehta hai. **Ye part theme ka hai, chheda nahi gaya.**

Jo theme me nahi tha — **drawer band karne ka koi tareeka**. Panel content ke upar khulta
hai (z-index 3) aur peeche kuch nahi hota. Isliye add kiya:

| File | Kya |
|---|---|
| `app-custom.css` | Backdrop (`#main-wrapper.menu-toggle::before`), `max-width: 85vw`, scroll lock |
| `assets/js/mobile-nav.js` | Backdrop tap · menu link tap · Escape se close; body scroll lock |

```js
// Parent items (has-arrow) sirf submenu kholte hain - unpar band nahi karna
if (link.classList.contains('has-arrow')) return;
```

Sab kuch overlay breakpoint ke andar scoped hai — desktop par `.menu-toggle` ka matlab
"sidebar ko icons me collapse karo" hai, jo bilkul pehle jaisa chalta rehta hai.

### Touch targets

Width breakpoint ke bajaye `pointer: coarse` par keyed hai — kyunki touchscreen laptop ko
bhi chahiye, aur chhoti desktop window ko nahi:

```css
@media (pointer: coarse) {
    .btn, .form-control, .form-select { min-height: 2.75rem; }  /* 44px */
    .table .btn-square { min-width: 2.5rem; min-height: 2.5rem; }
}
```

Table ka "⋮" row-action button har table ka sabse chhota tap target hai — usko explicitly
size diya gaya hai.

---

## Naya list screen kaise banayein

```blade
{{-- 1. Card par data-ajax-root --}}
<div class="card" data-ajax-root>

    {{-- 2. Filter bar --}}
    <div class="card-header d-block pb-2">
        <div class="row filter-bar align-items-start">
            <div class="col-12 col-md-6 col-xl-3 mb-3">
                <label class="form-label" for="mySearch">Search</label>
                <input type="text" id="mySearch" class="form-control"
                       data-search-param="q" value="{{ $filters['q'] }}">
            </div>
            {{-- ...aur filters --}}
        </div>
    </div>

    {{-- 3. Region + partial --}}
    <div class="ajax-region">
        @include('mymodule._table')
    </div>
</div>
```

```php
// 4. Controller
public function index(Request $request): View
{
    $data = [
        'rows'    => $this->filtered($request)->paginate($this->perPage($request))->withQueryString(),
        'filters' => ['q' => $this->strParam($request, 'q')],
    ];

    if ($request->ajax()) {
        return view('mymodule._table', $data);
    }
    return view('mymodule.index', $data);
}
```

`filter-bar` class saare controls ko ek hi height par align kar deti hai (input, Select2,
button — teeno ki metrics alag hoti hain).
