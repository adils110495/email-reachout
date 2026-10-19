[← Docs index](../README.md)

# Module: GMB Leads

**URL:** `/gmb-leads` · **Module key:** `gmb_leads`

Wo businesses jinki Google Business Profile hai lekin **website nahi** — website banwane ke
liye sabse achhe prospects. Inke paas phone hota hai, email nahi, isliye inse baat WhatsApp
par hoti hai ([whatsapp.md](whatsapp.md)).

---

## Leads kahan se aate hain

| Tarika | Kaise | `place_id` |
|---|---|---|
| **Find** (search card) | Keyword + category → `GmbSearchJob` → SerpAPI Google Maps, max 3 pages, sirf bina website wale save ([settings.md](settings.md) › SerpAPI) | Google ka (`ChIJ…`) — same business dobara save nahi hota |
| **Add GMB Lead** (header button) | Haath se — kisi aur tarah mila business | `manual-<uuid>` (`GmbLead::MANUAL_PREFIX`) — koi search isse match nahi karegi. List me **"Added manually"** badge |

## Add / Edit

Header ka **Add GMB Lead** aur har row ke `⋮` menu ka **Edit** — dono ek hi modal
(`#gmbLeadModal`, `gmb-leads/index.blade.php`), script add / edit mode set karti hai.

| Field | Rule |
|---|---|
| Business name | **Zaroori**, max 255 |
| Type | e.g. "Construction company" |
| Category | Apni categories me se (`existsRule` — dusre user ki category nahi chalegi) |
| Phone | WhatsApp ke liye zaroori — bina phone wali row bulk me tick nahi hoti |
| Address | max 255 |
| Rating / Reviews | 0–5 (0.1 step) / 0 ya zyada |
| Google Maps link | Sirf `http(s)://` — ye "View on Maps" link banta hai, `javascript:` link click par chal jaata |
| Keyword | Kaise mila (search box bhi isi par dhoondhta hai). Form me field ka naam `lead_keyword` hai, taaki galat save upar ke search box ka "keyword" na bhar de |

- Form poora record hai — **khaali chhodi field saaf ho jaati hai** (Edit me phone hataya = phone hat gaya).
- Search se aaye lead ka `place_id` edit par **nahi badalta** — wo agli search me dobara save nahi hoga.
- Galat input → page wapas, modal **usi mode me** (add ya kis lead ka edit) khulta hai, likha hua bacha rehta hai, field ke neeche error.
- Save ke baad wahi filtered list (`_redirect_back`).
- Owner scope: dusre user ka lead edit = 404.

## Routes

```php
GET     /gmb-leads            gmb-leads.index
GET     /gmb-leads/export     gmb-leads.export
POST    /gmb-leads/search     gmb-leads.search     Find (background job)
POST    /gmb-leads            gmb-leads.store      Add
PUT     /gmb-leads/{id}       gmb-leads.update     Edit
DELETE  /gmb-leads/{id}       gmb-leads.destroy
POST    /gmb-leads/whatsapp   gmb-leads.whatsapp   Bulk WhatsApp (module:whatsapp)
```

## Row menu

Edit · Send WhatsApp (phone ho to) · View on Maps (link ho to) · Mark as Deal · Delete

## Bulk WhatsApp

**WhatsApp N selected** = sirf tick ki hui rows. **Bulk WhatsApp** = current filters ki saari
leads (tick ho tab bhi). Detail: [whatsapp.md](whatsapp.md) › Bulk send.

## Files

```
app/Http/Controllers/GmbLeadController.php   list, filters, find, add / edit, delete, export, bulk WhatsApp
app/Models/GmbLead.php                       MANUAL_PREFIX, isManual()
app/Jobs/GmbSearchJob.php                    Find - SerpAPI, background
app/Services/GmbFinderService.php            SerpAPI Google Maps, bina website wale
resources/views/gmb-leads/index.blade.php    search card, list, Add / Edit modal
resources/views/gmb-leads/_table.blade.php   AJAX partial (rows, Edit button)
```
