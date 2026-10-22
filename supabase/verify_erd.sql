-- Read-only live verification for the complete Notezy ERD.

with expected(table_name) as (
  select unnest(array[
    'users', 'notes', 'labels', 'note_labels', 'note_shares',
    'note_pin_unlocks', 'note_attachments', 'note_collab_presence',
    'ai_conversations', 'ai_messages', 'ai_pending_actions',
    'ai_rate_limits', 'timetable', 'timetable_shares',
    'hosting_github_connections', 'hosting_render_connections',
    'hosting_deployments', 'hosting_custom_domains', 'flashcards',
    'quiz_results', 'chat_messages', 'plans', 'entitlements',
    'subscriptions', 'usage_records', 'premium_trials', 'service_outbox'
  ]::text[])
), actual as (
  select c.relname as table_name, c.relrowsecurity as rls_enabled
  from pg_class c
  join pg_namespace n on n.oid = c.relnamespace
  where n.nspname = 'public' and c.relkind = 'r'
)
select
  e.table_name,
  (a.table_name is not null) as table_exists,
  coalesce(a.rls_enabled, false) as rls_enabled
from expected e
left join actual a using (table_name)
order by e.table_name;
