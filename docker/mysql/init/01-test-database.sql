-- Container ilk dəfə yaradılanda (volume boş olanda) bir dəfə işləyir.
-- Testlər əsas database-i silməsin deyə ayrıca test database-i yaradırıq.
CREATE DATABASE IF NOT EXISTS barbershop_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON barbershop_test.* TO 'barbershop_user'@'%';
FLUSH PRIVILEGES;
