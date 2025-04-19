CREATE TABLE schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  board_id VARCHAR(50) NOT NULL,
  gpio VARCHAR(10) NOT NULL,
  scheduled_time DATETIME NOT NULL,
  action VARCHAR(10) NOT NULL,  -- e.g., "on" or "off"
  executed TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `created_at`) VALUES
(1, 'abelahmed1234@gmail.com', '12345', '2025-01-12 12:52:49');



--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);


--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
