-- Secure-by-default RLS baseline for the Notezy public schema.
--
-- The existing application uses its own integer user IDs and PHP sessions, not
-- Supabase Auth UUIDs. Therefore this migration intentionally enables RLS but
-- does not create anon/authenticated policies. Server-side direct PostgreSQL
-- access remains possible with an appropriate database role; Data API access
-- stays denied until an explicit authentication mapping is implemented.

do $$
declare
  table_name text;
begin
  foreach table_name in array array[
    'users',
    'notes',
    'labels',
    'note_labels',
    'note_shares',
    'note_pin_unlocks',
    'note_attachments',
    'note_collab_presence',
    'ai_conversations',
    'ai_messages',
    'ai_pending_actions',
    'ai_rate_limits',
    'timetable',
    'timetable_shares',
    'hosting_github_connections',
    'hosting_render_connections',
    'hosting_deployments',
    'hosting_custom_domains',
    'flashcards',
    'quiz_results',
    'chat_messages',
    'plans',
    'entitlements',
    'subscriptions',
    'usage_records',
    'premium_trials',
    'service_outbox'
  ]
  loop
    execute format('alter table public.%I enable row level security', table_name);
  end loop;
end
$$;

-- Catalog assertions make a partial deployment fail visibly in CI/SQL Editor.
do $$
declare
  unsecured_count integer;
begin
  select count(*)
    into unsecured_count
  from pg_class c
  join pg_namespace n on n.oid = c.relnamespace
  where n.nspname = 'public'
    and c.relkind = 'r'
    and c.relname = any (array[
      'users', 'notes', 'labels', 'note_labels', 'note_shares',
      'note_pin_unlocks', 'note_attachments', 'note_collab_presence',
      'ai_conversations', 'ai_messages', 'ai_pending_actions',
      'ai_rate_limits', 'timetable', 'timetable_shares',
      'hosting_github_connections', 'hosting_render_connections',
      'hosting_deployments', 'hosting_custom_domains', 'flashcards',
      'quiz_results', 'chat_messages', 'plans', 'entitlements',
      'subscriptions', 'usage_records', 'premium_trials', 'service_outbox'
    ])
    and not c.relrowsecurity;

  if unsecured_count <> 0 then
    raise exception '% Notezy tables still have RLS disabled', unsecured_count;
  end if;
end
$$;
