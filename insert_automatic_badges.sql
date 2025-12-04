-- Insert predefined automatic badges
INSERT IGNORE INTO badgestbl (badge_name, badge_description, badge_instructions, badge_icon, badge_color, badge_category) VALUES 
('Event Creator I', 'Create your first event', 'Create and get approval for your first event', 'fas fa-calendar-plus', '#4CAF50', 'Milestone'),
('Event Creator V', 'Create 5 events', 'Create and get approval for 5 events', 'fas fa-calendar-check', '#2E7D32', 'Milestone'),
('Event Creator X', 'Create 10 events', 'Create and get approval for 10 events', 'fas fa-calendar-alt', '#1B5E20', 'Milestone'),
('Event Creator L', 'Create 50 events', 'Create and get approval for 50 events', 'fas fa-crown', '#FFD700', 'Milestone'),
('Eco Points Collector I', 'Earn 100 total eco points', 'Accumulate 100 eco points through various activities', 'fas fa-coins', '#FFC107', 'Environmental'),
('Eco Points Collector V', 'Earn 500 total eco points', 'Accumulate 500 eco points through various activities', 'fas fa-gem', '#FF9800', 'Environmental'),
('Eco Points Collector X', 'Earn 1000 total eco points', 'Accumulate 1000 eco points through various activities', 'fas fa-trophy', '#FF5722', 'Environmental'),
('Eco Points Master', 'Earn 5000 total eco points', 'Accumulate 5000 eco points through various activities', 'fas fa-medal', '#9C27B0', 'Environmental'),
('Tree Planter', 'Participate in tree planting events', 'Join and contribute to tree planting activities', 'fas fa-tree', '#4CAF50', 'Environmental'),
('Mangrove Guardian', 'Outstanding dedication to mangrove conservation', 'Show exceptional commitment to mangrove protection', 'fas fa-shield-alt', '#00BCD4', 'Hero'),
('Community Leader', 'Exceptional leadership in environmental activities', 'Demonstrate outstanding leadership in community activities', 'fas fa-users', '#3F51B5', 'Hero'),
('Event Attendee', 'Attend community events', 'Participate in community environmental events', 'fas fa-calendar-check', '#8BC34A', 'Collection'),
('Conservation Hero', 'Outstanding contribution to conservation efforts', 'Make significant contributions to environmental conservation', 'fas fa-leaf', '#607D8B', 'Hero'),
('Volunteer Champion', 'Dedicated volunteer service', 'Show consistent dedication to volunteer work', 'fas fa-hands-helping', '#795548', 'Hero');
