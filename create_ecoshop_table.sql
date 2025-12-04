CREATE TABLE ecoshop_itemstbl (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    item_emoji VARCHAR(10) NOT NULL DEFAULT '🎁',
    item_description TEXT,
    points_required INT NOT NULL DEFAULT 100,
    stock_quantity INT DEFAULT NULL,
    is_available TINYINT(1) DEFAULT 1,
    category VARCHAR(100) DEFAULT 'general',
    image_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert existing reward items into the database
INSERT INTO ecoshop_itemstbl (item_name, item_emoji, points_required, category, item_description) VALUES
('Reusable Shopping Bag', '🛍️', 100, 'accessories', 'Eco-friendly reusable shopping bag to reduce plastic waste'),
('Mangrove Seed Kit', '🌿', 200, 'conservation', 'Complete kit with mangrove seeds and planting instructions'),
('Eco Water Bottle', '💧', 300, 'accessories', 'Sustainable water bottle made from recycled materials'),
('Local Store Discount', '🏷️', 500, 'vouchers', '20% discount voucher for partnered local eco-friendly stores'),
('Mangrove Adoption Certificate', '🌱', 1000, 'conservation', 'Official certificate for adopting and caring for a mangrove tree'),
('Eco Backpack', '🎒', 1500, 'accessories', 'Durable backpack made from sustainable materials'),
('Conservation Guidebook', '📚', 800, 'education', 'Comprehensive guide to mangrove conservation practices'),
('Tree Planting Kit', '🌳', 600, 'conservation', 'Complete kit for planting and nurturing trees'),
('Eco Cafe Voucher', '☕', 400, 'vouchers', 'Free drink voucher at partnered eco-friendly cafes'),
('Organic Cotton Shirt', '👕', 1200, 'apparel', 'Comfortable shirt made from 100% organic cotton'),
('Eco-friendly Soap Set', '🧴', 700, 'personal_care', 'Natural soap set made with organic ingredients'),
('Bamboo Pen Set', '🖋️', 300, 'accessories', 'Sustainable writing set made from bamboo');
