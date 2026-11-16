# Questions from Requirements — worklog_automation

I documented the existing code as-is (BRD.md + REQUIREMENTS.md v1). A few things the code
cannot tell me. Each has my assumed default — I'll proceed on these unless you say otherwise.

1. **Who is this for, and what is "maintenance and fixes" aiming at?** The brief just says
   "registered for maintenance and fixes." Is there a specific bug list or new feature you want,
   or should I only keep the baseline docs current for now?
   *Default: baseline only; wait for a specific task.*

2. **Submission identity (important).** Right now anyone who reaches `POST /worklogs` or
   `POST /tasks` can submit for ANY user by changing `user_id` in the request (employees don't
   log in, and user IDs are sequential). Is this acceptable (trusted internal network) or should
   we tie submissions to the UUID / add a real check?
   *Default: treat as a security gap to fix; I'll raise it as a change when you confirm.*

3. **UUID links — expiry/rotation.** The personal link never expires. Do you want links to
   expire or be rotatable (e.g. if an employee leaves)?
   *Default: keep permanent links for now.*

4. **Employee portal.** There's unfinished code for an employee "my recent worklogs" view that
   is unreachable. Do you want employees to log in and see their own history, or keep them on
   the UUID form only?
   *Default: keep UUID form only; no employee login.*

5. **Editing/deleting a submitted worklog.** Today a log cannot be corrected once submitted, and
   duplicates aren't prevented. Do you want edit/delete and/or duplicate protection?
   *Default: leave as-is unless requested.*

6. **What does n8n do with the data?** Knowing the downstream automation (reports, Slack/email,
   sheets) helps keep the webhook payload correct. Can you share what the n8n workflow does?
   *Default: I assume reporting/notification and keep the payload unchanged.*

7. **Which seed "Project For"/projects and users are real/active?** The seeders list Avaib, QC,
   TradeSora, Remoteface and five user emails. Are these the live set, and is the
   `waseem.iqbal@avaib.com` admin the real admin account?
   *Default: assume they are the live catalogue; admin password must be changed from the seeded
   default.*

8. **Hosting & who owns the n8n secret.** Is this running on a company server or local XAMPP,
   and has the `N8N_WEBHOOK_SECRET` currently in `.env` been shared anywhere (it should be
   rotated if so)?
   *Default: assume XAMPP/company server; recommend rotating the secret.*

9. **Language of user-facing text.** The login error is in Roman Urdu; everything else is
   English. Should all UI text be English, or keep Roman Urdu where present?
   *Default: standardise user-facing text to English.*

10. **Any per-day or approval rules?** Should there be a maximum hours-per-day, or a
    manager-approval step before logs count?
    *Default: no daily cap and no approval step (as built today).*
