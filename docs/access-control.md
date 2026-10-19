[← Docs index](README.md)

# Access Control — user-based workspaces

Har user ka **apna alag workspace** hai: leads, emails, templates, categories,
platforms, addresses, SMTP/IMAP, bulks, verifications, deals, notifications — sab
uska apna. Ek exception: **Super Admin**, jo poora system dekh aur manage kar sakta hai.

Requirement doc: [user-modules.md](user-modules.md). Ye page batata hai ki is app
me wo kaise implement hua.

---

## Do layer — dono server par

| Layer | Kya rokta hai | Kahan hai |
|---|---|---|
| **Module access** | User ke paas wo module hi nahi hai (Finder, Leads, Deals…) | `module:` route middleware → `EnsureModuleAccess` |
| **Owner scope** | Module hai, par record kisi aur user ka hai | `BelongsToUser` trait → `OwnedByUserScope` (global scope) |

Sidebar se link chhupana sirf UX hai. Asli rok ye dono layer hain — URL type karna,
AJAX call, `?id=` badalna, form me `category_id` badalna, kuch kaam nahi karta.

---

## Owner scope — ek jagah, har query par

```
Lead::findOrFail(6)      →   WHERE id = 6 AND leads.user_id = <me>
Lead::whereIn(ids)->delete()   →   sirf meri rows delete
Lead::orderBy(...)->get() (export)   →   sirf meri rows
whereHas('lead', ...)    →   sub-query bhi scoped
```

Kisi controller me alag se check nahi likhna padta. Naya owned model banana ho to:

1. table me `user_id` (FK → users, cascade) — migration
2. model me `use BelongsToUser;`

Bas. Create par `user_id` apne aap bhar jata hai, aur `user_id` kabhi fillable nahi
hota — koi form use badal nahi sakta.

### Kaun kya dekhta hai — `UserAccessService`

| Situation | Query |
|---|---|
| Web request, normal user | sirf uski rows |
| Web request, Super Admin | sab rows (koi filter nahi) |
| `runAs($user, fn)` | **sirf** us user ki rows — Super Admin ho tab bhi |
| `asSystem(fn)` | sab rows — sirf scheduler / tracking pixel / WhatsApp webhook lookup ke liye |
| Koi context nahi (console, job bina owner) | **kuch nahi** (fail closed) — aur create par exception |

### `mine()` — jab Super Admin ko bhi sirf apna chahiye

Kuch cheezein personal hain, Super Admin ke liye bhi:

| Kahan | Kyun |
|---|---|
| Mail Settings (SMTP/IMAP) | Admin apne mailbox se bheje, kisi user ke credentials se nahi |
| WhatsApp Settings | Same — admin apne number/token se, kisi user ke token se nahi |
| SerpAPI Settings | Search apne SerpAPI credits se — kisi user ki key admin ko nahi dikhti, na use hoti hai |
| Notification bell | Har kisi ke job notifications admin ki bell me nahi aane chahiye |
| Verifier "Clear history" | Ek click se sab users ki history na ude |

### Validation

`exists:categories,id` DB query builder se chalta hai — global scope wahan nahi
lagta. Isliye har owned-table rule `UserAccessService` se aata hai:

```php
'category_id' => ['nullable', $this->access()->existsRule('categories')],
'name'        => [$this->access()->uniqueRule('categories', 'name')],   // per-user unique
```

---

## Super Admin kisi aur user ke record par kaam kare

Record jis user ka hai, kaam **usi ke naam par** hota hai (`runAs(owner)`):

| Action | Kis ke naam par |
|---|---|
| Lead ko email bhejna | Lead ka owner — uska SMTP, uski address, uski Email Activity |
| GMB lead / deal ko WhatsApp | Record ka owner — uska WhatsApp number/token, uska WhatsApp log |
| Deals ka Bulk Send (email) | Deals ka owner — uska SMTP, Sent folder, Email Activity. Template attachments signed-in user ke template se hi lete hain |
| Finder result "Save" | Row ka owner — lead usi ke workspace me |
| "Mark as Deal" | Lead / GMB lead ka owner |
| Bulk retry | Bulk ka owner |

---

## Background jobs

Queue worker me koi signed-in user nahi hota. Isliye har job batata hai ki wo kis
user ka hai, aur `ActAsOwner` job middleware use usi user ke roop me chalata hai:

| Job | Owner kahan se |
|---|---|
| `FindLeadsJob`, `FinderSearchJob`, `GmbSearchJob` | `userId` (dispatch karte waqt) |
| `ProcessBulkJob` | `userId` = bulk ka owner (har chunk aage pass karta hai) |
| `ScrapeLeadEmailJob`, `SendEmailJob` | `lead->user_id` |
| `SendWhatsappMessageJob`, `SendBulkEmailJob` | `userId` = bulk ke contacts ka owner (sab ek hi user ke hone chahiye) |

- Bina owner wala job (deploy se pehle queue me pada) → **fail** hota hai, chupchap kuch galat nahi karta.
- Owner delete ho chuka → job drop.
- `failed()` middleware se nahi guzarta, isliye wo `asOwner()` me likhta hai.

### Scheduler / artisan

| Command | Scope |
|---|---|
| `emails:check-replies` (har 10 min) | `asSystem` → har owner ka inbox **usi ke IMAP** se |
| `leads:find "kw" --user=jane` | `--user` ke workspace me (default: pehla super admin) |
| `emails:send [--user=jane]` | sab users, ya sirf `--user` |

---

## Modules

`users.modules` (JSON): `null` = sab modules (naye signup ka default), list = sirf wo.
Keys `User::MODULES` me hain, wahi `config/navigation.php` (`'module' => ...`) aur
routes (`module:leads`) use karte hain.

- Dashboard aur notification bell hamesha available.
- `bulks.show / status / export` → `module:bulks,verifier` (Verifier ki 10+ list bulk ban jati hai).
- **Branding** aur **Users** → sirf Super Admin (`can:manage-branding`, `can:manage-users`).
- Super Admin `Gate::before` se har check pass karta hai.

---

## Users module — Settings › Users (sirf Super Admin)

- User banana / edit / deactivate / delete, role aur modules set karna
- Har user ki **Email Activity** (`?user=<id>` filter)
- `role`, `is_active`, `modules` fillable nahi hain — sirf `UserController` `forceFill` se likhta hai. Signup form inhe set nahi kar sakta.
- Admin apna role / status nahi badal sakta, aur khud ko delete nahi kar sakta (lockout se bachne ke liye).
- **Delete = poora workspace delete** (FK cascade). Uploaded files (`storage/app/...`) disk par reh jati hain.
- Deactivated user: login nahi kar sakta, aur khula session agle request par khatam (`active` middleware).

Signup open hai: naya user = normal user, saare modules, khaali workspace + default platforms.
**Email zaroori hai** (aur unique) — signup par bhi aur Users module me bhi. Login username
ya email, dono se hota hai.

### Username

| Kahan | Kya hota hai |
|---|---|
| Signup | Form me username **nahi** hai. `User::uniqueUsernameFrom()` email se banata hai: `jane.doe+news@x.com` → `jane_doe`; liya hua ho to `jane_doe2`, `jane_doe3`… (email ka hissa 3 akshar se chhota ho to naam se) |
| My Profile (`/profile`) | User khud badal sakta hai — **30 din me ek baar** (`User::USERNAME_CHANGE_DAYS`). Pehla change turant allowed (`username_changed_at` null). Rule server par hai: ek conditional `UPDATE`, isliye do jaldi-jaldi submit bhi limit nahi tod sakte |
| Settings › Users | Super Admin kabhi bhi badal sakta hai; isse user ka 30 din wala change **use nahi** hota |

Username hamesha unique hai (DB index + validation), aur `alpha_dash` hai — `@` / `.` nahi,
isliye login par username kabhi email jaisa nahi dikhta.

---

## Migration

| Migration | Kya karta hai |
|---|---|
| `2026_10_14_000002_add_access_columns_to_users_table` | `role`, `is_active`, `modules`; `superadmin` account → `super_admin` |
| `2026_10_14_000003_add_user_id_to_owned_tables` | 13 tables par `user_id`; **purana saara data super admin ko**; per-user unique keys; baaki existing users ko default platforms |
| `2026_10_14_000004_add_username_changed_at_to_users_table` | `users.username_changed_at` — Profile par username ka 30 din wala rule |

Per-user unique: `categories.name`, `mail_settings.type`, `gmb_leads.place_id`, `finder_results.domain`.
Global rehta hai: `app_settings` (branding), `bulk_items` (bulk ke through scoped).
