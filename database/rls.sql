-- ============================================
-- ROW LEVEL SECURITY (RLS) POLICIES
-- Defense-in-depth: prevents direct database 
-- access via anon key. Your backend uses the 
-- service_role key which bypasses RLS.
-- ============================================

-- Enable RLS on all tables
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
ALTER TABLE teams ENABLE ROW LEVEL SECURITY;
ALTER TABLE challenges ENABLE ROW LEVEL SECURITY;
ALTER TABLE challenge_files ENABLE ROW LEVEL SECURITY;
ALTER TABLE solves ENABLE ROW LEVEL SECURITY;
ALTER TABLE submissions ENABLE ROW LEVEL SECURITY;
ALTER TABLE admin_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE site_content ENABLE ROW LEVEL SECURITY;

-- Block ALL access via anon/authenticated roles
-- (Your backend uses service_role which bypasses these)

CREATE POLICY "Deny all on events" ON events FOR ALL USING (false);
CREATE POLICY "Deny all on teams" ON teams FOR ALL USING (false);
CREATE POLICY "Deny all on challenges" ON challenges FOR ALL USING (false);
CREATE POLICY "Deny all on challenge_files" ON challenge_files FOR ALL USING (false);
CREATE POLICY "Deny all on solves" ON solves FOR ALL USING (false);
CREATE POLICY "Deny all on submissions" ON submissions FOR ALL USING (false);
CREATE POLICY "Deny all on admin_logs" ON admin_logs FOR ALL USING (false);
CREATE POLICY "Deny all on announcements" ON announcements FOR ALL USING (false);
CREATE POLICY "Deny all on site_content" ON site_content FOR ALL USING (false);
