-- --------------------------------------------------------
-- Insert Default Data
-- --------------------------------------------------------

--
-- Dumping data for table `army`
--

INSERT INTO
    `bf2web_account_groups`(`id`, `title`, `banned`, `is_admin`, `is_owner`)
VALUES
    (1, 'Super Admin', 0, 1, 1),
    (2, 'Admin', 0, 1, 0),
    (3, 'User', 0, 0, 0),
    (4, 'Banned User', 1, 0, 0);

INSERT INTO `bf2web_site_navigation`
    (`id`, `parent_id`, `label`, `title`, `url`, `route_names`, `icon`, `sort_order`, `separator_below`)
VALUES
     (1, NULL, 'Dashboard', 'Dashboard', '/', '["home-page"]', NULL, 10, 0),
     (2, NULL, 'News', 'News', '/news', '["news-home","news-view"]', NULL, 20, 0),
     (3, NULL, 'Rankings', 'Rankings', '/rankings', '["rankings"]', NULL, 30, 0),
     (4, NULL, 'Servers', 'Servers', '/servers', '["servers-list","servers-view"]', NULL, 40, 0),
     (5, NULL, 'Awards & Ranks', 'Awards', '/requirements', '["requirements"]', NULL, 50, 0),
     (6, 3, 'My Profile', 'My Profile', '/players/view/', '["player-profile"]', 'profile', 10, 0),
     (7, 3, 'My Leaderboard', 'My Leaderboard', '/players/leaderboard', '["player-leaderboard"]', 'leaderboard', 20, 1),
     (8, 3, 'Search for Player', 'Search for Player', '/players/search', '["player-search"]', 'search', 30, 0);


INSERT INTO `bf2web_version`(`updateid`, `version`) VALUES (1, '0.0.1');