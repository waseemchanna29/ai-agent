# User Guide — School To-Do

A simple web app for keeping a personal to-do list. You create an account, log in,
and manage your own private list of tasks: add them, tick them off, and delete the
ones you no longer need. Only you can see your tasks.

> **Before you start:** your browser must have **JavaScript enabled**. Ticking a task
> complete and the "are you sure?" delete prompt both rely on it.

---

## 1. Opening the app

In your browser go to the address your administrator gave you:

- `http://127.0.0.1:8000` (when started with `php artisan serve`), or
- `http://localhost/todo-app/public` (when served by XAMPP Apache).

If you are not logged in, the app sends you to the **Log in** page.

---

## 2. Creating an account

1. On the Log in page, click **Register** (or go straight to `/register`).
2. Fill in the form:
   - **Name** — your display name, shown in the top bar.
   - **Email** — must be a valid address and not already registered.
     Enter it in **lowercase** (e.g. `me@example.com`), not `Me@Example.com`.
   - **Password** — at least **8 characters**.
   - **Confirm password** — must match the password exactly.
3. Click **Register**.

If everything is valid, your account is created, you are **logged in automatically**,
and you land on your (empty) task list. If something is wrong — e.g. the email is
already taken or the passwords don't match — the page shows a red
"Please fix the following" box listing what to correct. No account is created until
the form is valid.

---

## 3. Logging in and out

**Log in**

1. Go to the Log in page (`/login`).
2. Enter the **email** and **password** you registered with.
   Use the same email casing (lowercase) you used at registration.
3. (Optional) tick **Remember me** to stay logged in on this browser.
4. Click **Log in**.

Wrong email or password shows an error and does not log you in.

**Log out**

Click **Log out** in the top-right bar. Your session ends and you return to the
Log in page. Your task list is locked again until you log back in.

---

## 4. Managing your tasks

Everything happens on one page: **My tasks** (`/tasks`).

### Add a task

1. Type what you need to do in the **"What needs to be done?"** box.
2. Click **Add**.

The task appears at the **top** of the list (newest first). A short green
confirmation ("Task added.") is shown. An empty or spaces-only title is rejected
with a validation message — nothing is saved. Titles can be up to **255 characters**.

### See your tasks

The list shows only **your** tasks, newest first. Each row has:

- a **checkbox** on the left (completion state),
- the **task title**,
- a **Delete** button on the right.

If you have no tasks yet, you'll see *"No tasks yet. Add your first one above."*

### Mark a task complete / incomplete

Click the **checkbox** next to a task.

- Ticked → the task is **complete**: its title is shown with a line through it and
  greyed out.
- Un-tick it to mark it **not complete** again.

The change saves as soon as you click (the page refreshes itself).

### Delete a task

1. Click **Delete** next to the task.
2. A confirmation box asks *"Delete this task?"* — click **OK** to confirm or
   **Cancel** to keep it.

Deleting is **permanent** — there is no undo or recycle bin.

---

## 5. Managing users

Any logged-in user can open **Users** (top-right of the nav bar, next to your name) to
see and manage every account in the system — not just your own.

> **Note:** this version has no admin role yet, so this screen is open to **every**
> logged-in user, not just administrators.

### See all users

The Users page (`/users`) lists every account, newest first, 15 per page, showing
each person's **name** and **email**. Use **Add user** / **Edit** / **Delete** below.

### Add a user

1. On the Users page, click **Add user**.
2. Fill in **Name**, **Email** (valid, not already used), **Password** (min 8
   characters), and **Confirm password**.
3. Click **Create user**.

### Edit a user

1. Click **Edit** next to the user you want to change.
2. Update **Name** and/or **Email**.
3. **Password** is optional here — leave both password fields blank to keep the
   user's current password, or fill in a new password (min 8) and its confirmation
   to reset it.
4. Click **Save changes**.

### Delete a user

1. Click **Delete** next to a user.
2. Confirm the "Delete this user?" prompt.

Deleting a user is permanent and also deletes **all of that user's tasks**. You
**cannot delete the account you are currently logged in with** — the app blocks it.

---

## 6. Privacy of your tasks

Tasks are tied to your account. You can never see, change, or delete another
person's tasks, and they can never see yours. If you are logged out and try to open
the task page, the app redirects you to Log in first.

---

## 7. Troubleshooting

| What you see | Why | What to do |
|---|---|---|
| Sent to the Log in page unexpectedly | You're logged out or your session expired | Log in again |
| "The email field must be lowercase" at registration | The email had capital letters | Re-enter the email in lowercase |
| "The email has already been taken" | An account already uses that email | Log in instead, or use a different email |
| Ticking the checkbox does nothing | JavaScript is disabled | Enable JavaScript and reload |
| Delete happens with no confirmation prompt | JavaScript is disabled | Enable JavaScript so the confirm dialog appears |
| "Page Expired" (419) after a form sits open a long time | The security token expired | Reload the page and submit again |
| Trying to delete your own account does nothing / error | You can't delete the account you're logged in with | Log in as a different user to remove that account |

---

*Scope note:* this version has no password reset, no email verification, and no admin
area. If you forget your password, an administrator must help you at the database
level.

## Document Version History

| Version | Date | Author | Description of changes |
|---|---|---|---|
| 1 | 2026-11-12 | Technical Writer | Initial release |
| 2 | 2026-11-12 | Technical Writer | Added section 5 "Managing users" (add/edit/delete any account, no admin role yet) and a related troubleshooting row; renumbered sections 6-7. |
