-- phpMyAdmin SQL Dump
-- Database: nafl_db
-- بيانات تجريبية شاملة لمنصة نفل البيئية
-- كلمة المرور لكل المستخدمين التجريبيين: 123456

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- ========================================
-- جدول: users (المستخدمين)
-- ========================================
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `age` int(11) DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `total_points` int(11) NOT NULL DEFAULT '0',
  `profile_image_path` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- 12 مستخدم تجريبي (كلمة المرور للجميع: 123456)
INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `age`, `phone`, `total_points`, `profile_image_path`, `is_admin`) VALUES
(1,  'سارة الغامدي',     'sara@gmail.com',     '123456', 24, '0501111111', 72,  'sarah.jpeg',    0),
(2,  'محمد العتيبي',      'mohammed@gmail.com', '123456', 30, '0502222222', 120, 'muhammad.jpeg', 1),
(3,  'نورة الشمري',       'norah@gmail.com',    '123456', 27, '0503333333', 95,  'norah.jpeg',    1),
(4,  'فهد الدوسري',       'fahad@gmail.com',    '123456', 33, '0504444444', 80,  'fahad.jpeg',    1),
(5,  'ريم القحطاني',      'reem@gmail.com',     '123456', 26, '0505555555', 60,  'reeem.jpeg',    1),
(6,  'عبدالله الزهراني',  'abdullah@gmail.com', '123456', 35, '0506666666', 145, NULL,            1),
(7,  'منال الحربي',       'manal@gmail.com',    '123456', 22, '0507777777', 35,  NULL,            0),
(8,  'خالد السبيعي',      'khalid@gmail.com',   '123456', 29, '0508888888', 50,  NULL,            0),
    (9,  'لمى الرشيد',        'lama@gmail.com',     '123456', 19, '0509999999', 25,  NULL,            0),
(10, 'يوسف العمري',       'youssef@gmail.com',  '123456', 28, '0501212121', 105, NULL,            1),
(11, 'هند الصالح',        'hind@gmail.com',     '123456', 31, '0501313131', 90,  NULL,            0),
(12, 'مدير النظام',       'admin@gmail.com',    '123456', 40, '0500000000', 0,   NULL,            1);

-- ========================================
-- جدول: events (الفعاليات)
-- ========================================
CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
  `category` enum('Cleaning','Planting','Recycling','Awareness','Exhibition') COLLATE utf8_unicode_ci NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `contact_number` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `volunteer_hours` int(11) NOT NULL DEFAULT '0',
  `target_age_group` enum('Children','Teens','Adults','All') COLLATE utf8_unicode_ci NOT NULL,
  `points` int(11) NOT NULL DEFAULT '0',
  `max_participants` int(11) NOT NULL DEFAULT '50',
  `certificate_available` tinyint(1) NOT NULL DEFAULT '0',
  `image_path` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Approved',
  `attendance_code` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_by_user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- 14 فعالية متنوعة
INSERT INTO `events` (`event_id`, `title`, `description`, `category`, `event_date`, `start_time`, `end_time`, `location`, `contact_number`, `volunteer_hours`, `target_age_group`, `points`, `max_participants`, `certificate_available`, `image_path`, `status`, `attendance_code`, `created_by_user_id`) VALUES
(1,  'حملة تنظيف منتزه الملك عبدالله', 'انضم إلينا في حملة تنظيف شاملة لمنتزه الملك عبدالله. سنقوم بجمع النفايات وتصنيفها وإعادة تدويرها. جميع الأدوات والمعدات سيتم توفيرها.', 'Cleaning', '2026-07-15', '08:00:00', '12:00:00', 'الرياض، منتزه الملك عبدالله', '0501234567', 4, 'Adults', 10, 50, 1, 'https://images.unsplash.com/photo-1618477461853-cf6ed80faba5?w=900&q=80', 'Approved', 'NAFL001', 2),
(2,  'ورشة زراعة الأشجار في وادي حنيفة', 'شارك في زراعة 100 شجرة محلية في وادي حنيفة. سنتعلم كيفية زراعة الأشجار والعناية بها بشكل صحيح.', 'Planting', '2026-08-20', '09:00:00', '13:00:00', 'الرياض، وادي حنيفة', '0509876543', 4, 'Teens', 15, 40, 1, 'https://images.unsplash.com/photo-1466692476868-aef1dfb1e735?w=900&q=80', 'Approved', 'NAFL002', 3),
(3,  'ورشة إعادة تدوير المخلفات', 'تعلم كيفية إعادة تدوير المواد المختلفة وصنع منتجات مفيدة من المخلفات. ورشة عملية تفاعلية.', 'Recycling', '2026-09-25', '10:00:00', '14:00:00', 'الرياض، مركز الملك عبدالعزيز الثقافي', '0503456789', 4, 'All', 12, 35, 1, 'https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?w=900&q=80', 'Approved', 'NAFL003', 4),
(4,  'حملة توعية بالطاقة المتجددة', 'محاضرات وورش عمل حول الطاقة الشمسية والطاقة المتجددة وكيفية تطبيقها في المنازل.', 'Awareness', '2026-10-28', '11:00:00', '15:00:00', 'الرياض، جامعة الملك سعود', '0507654321', 4, 'Adults', 8, 80, 1, 'https://images.unsplash.com/photo-1509391366360-2e959784a276?w=900&q=80', 'Approved', 'NAFL004', 5),
(5,  'يوم نظافة حي السفارات', 'مبادرة مجتمعية لتنظيف وتجميل حي السفارات بمشاركة السكان والمتطوعين.', 'Cleaning', '2026-05-05', '07:30:00', '11:30:00', 'الرياض، حي السفارات', '0502345678', 4, 'Teens', 10, 50, 1, 'https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?w=900&q=80', 'Approved', 'NAFL005', 2),
(6,  'معرض الاستدامة البيئية', 'معرض شامل للحلول البيئية المستدامة والمنتجات الصديقة للبيئة مع ورش عمل متنوعة.', 'Exhibition', '2026-11-10', '10:00:00', '18:00:00', 'الرياض، مركز المعارض', '0508765432', 8, 'All', 5, 150, 1, 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?w=900&q=80', 'Approved', 'NAFL006', 3),
(7,  'تنظيف شواطئ السيف', 'مبادرة لتنظيف شواطئ المنطقة الشرقية ورفع الوعي البيئي حول التلوث البحري.', 'Cleaning', '2026-05-15', '06:00:00', '10:00:00', 'الدمام، شاطئ السيف', '0501234500', 4, 'All', 12, 60, 1, 'https://images.unsplash.com/photo-1605600659908-0ef719419d41?w=900&q=80', 'Approved', 'NAFL007', 6),
(8,  'يوم التشجير الوطني', 'مشاركة في زراعة 500 شجرة ضمن مبادرة السعودية الخضراء في حديقة الملك سلمان.', 'Planting', '2026-05-20', '08:00:00', '12:00:00', 'الرياض، حديقة الملك سلمان', '0501122334', 4, 'All', 20, 100, 1, 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?w=900&q=80', 'Approved', 'NAFL008', 6),
(9,  'ورشة صناعة الكمبوست', 'تعلم كيفية تحويل المخلفات العضوية إلى سماد طبيعي مفيد للنباتات.', 'Recycling', '2026-05-22', '15:00:00', '18:00:00', 'الرياض، مركز التميز البيئي', '0504455667', 3, 'Adults', 10, 25, 1, 'https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?w=900&q=80', 'Approved', 'NAFL009', 10),
(10, 'محاضرة: التغير المناخي وأثره', 'محاضرة توعوية للشباب حول التغير المناخي وكيفية المساهمة في تقليل آثاره.', 'Awareness', '2026-05-25', '19:00:00', '21:00:00', 'الرياض، مكتبة الملك فهد الوطنية', '0506677889', 2, 'Teens', 7, 100, 0, 'https://images.unsplash.com/photo-1569163139394-de4798aa62b6?w=900&q=80', 'Approved', 'NAFL010', 10),
(11, 'مهرجان الطبيعة للأطفال', 'فعالية ترفيهية تعليمية للأطفال حول حماية البيئة والحياة البرية.', 'Awareness', '2026-06-01', '09:00:00', '13:00:00', 'الرياض، حديقة السلام', '0508899001', 4, 'Children', 8, 200, 1, 'https://images.unsplash.com/photo-1500673922987-e212871fec22?w=900&q=80', 'Approved', 'NAFL011', 11),
(12, 'حملة جمع البلاستيك من الأحياء', 'مبادرة لجمع المخلفات البلاستيكية من 5 أحياء في الرياض وإعادة تدويرها.', 'Recycling', '2026-06-05', '07:00:00', '11:00:00', 'الرياض، عدة أحياء', '0509988776', 4, 'Adults', 15, 80, 1, 'https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?w=900&q=80', 'Approved', 'NAFL012', 4),
(13, 'معرض الابتكار الأخضر', 'معرض لعرض الابتكارات والحلول التقنية الصديقة للبيئة من الشباب السعودي.', 'Exhibition', '2026-06-12', '10:00:00', '20:00:00', 'الرياض، مركز الملك فهد الثقافي', '0501122335', 8, 'All', 6, 300, 1, 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?w=900&q=80', 'Approved', 'NAFL013', 3),
(14, 'تنظيف وادي نمار', 'حملة تطوعية لتنظيف وادي نمار من النفايات وحماية البيئة الطبيعية.', 'Cleaning', '2026-06-18', '06:30:00', '10:30:00', 'الرياض، وادي نمار', '0502211443', 4, 'Adults', 11, 70, 1, 'https://images.unsplash.com/photo-1618477461853-cf6ed80faba5?w=900&q=80', 'Pending', 'NAFL014', 5);

-- ========================================
-- جدول: registrations (التسجيلات)
-- ========================================
CREATE TABLE `registrations` (
  `registration_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `registration_date` date NOT NULL,
  `attendance_status` enum('Registered','Attended') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Registered',
  `points_awarded` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `registrations` (`registration_id`, `user_id`, `event_id`, `registration_date`, `attendance_status`, `points_awarded`) VALUES
(1, 1, 1,  '2026-04-01', 'Registered', 0),
(2, 1, 2,  '2026-04-02', 'Registered', 0),
(3, 1, 4,  '2026-04-05', 'Attended',   8),
(4, 1, 8,  '2026-05-01', 'Registered', 0),
(5, 2, 3,  '2026-04-03', 'Attended',   12),
(6, 2, 6,  '2026-04-15', 'Registered', 0),
(7, 3, 1,  '2026-04-01', 'Attended',   10),
(8, 3, 7,  '2026-04-20', 'Registered', 0),
(9, 4, 2,  '2026-04-04', 'Attended',   15),
(10, 4, 10,'2026-05-10', 'Registered', 0),
(11, 5, 3, '2026-04-06', 'Attended',   12),
(12, 5, 11,'2026-05-20', 'Registered', 0),
(13, 6, 4, '2026-04-08', 'Attended',   8),
(14, 6, 9, '2026-05-12', 'Registered', 0),
(15, 7, 1, '2026-04-02', 'Attended',   10),
(16, 7, 5, '2026-04-25', 'Registered', 0),
(17, 8, 6, '2026-04-18', 'Attended',   5),
(18, 8, 8, '2026-05-05', 'Registered', 0),
(19, 9, 11,'2026-05-22', 'Registered', 0),
(20, 10, 2,'2026-04-07', 'Attended',   15),
(21, 10, 13,'2026-05-30','Registered', 0),
(22, 11, 3,'2026-04-10', 'Attended',   12),
(23, 11, 12,'2026-05-25','Registered', 0);

-- ========================================
-- جدول: certificates (الشهادات)
-- ========================================
CREATE TABLE `certificates` (
  `certificate_id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `issue_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `certificates` (`certificate_id`, `registration_id`, `issue_date`) VALUES
(1, 3,  '2026-04-29'),
(2, 5,  '2026-04-26'),
(3, 7,  '2026-04-16'),
(4, 9,  '2026-04-21'),
(5, 11, '2026-04-26'),
(6, 13, '2026-04-29'),
(7, 15, '2026-04-16'),
(8, 17, '2026-05-11'),
(9, 20, '2026-04-21'),
(10, 22,'2026-04-26');

-- ========================================
-- المفاتيح والفهارس
-- ========================================
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`);

ALTER TABLE `registrations`
  ADD PRIMARY KEY (`registration_id`),
  ADD UNIQUE KEY `user_event_unique` (`user_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

ALTER TABLE `certificates`
  ADD PRIMARY KEY (`certificate_id`),
  ADD UNIQUE KEY `registration_id` (`registration_id`);

-- AUTO_INCREMENT
ALTER TABLE `users`         MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT,         AUTO_INCREMENT=13;
ALTER TABLE `events`        MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT,        AUTO_INCREMENT=15;
ALTER TABLE `registrations` MODIFY `registration_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;
ALTER TABLE `certificates`  MODIFY `certificate_id` int(11) NOT NULL AUTO_INCREMENT,  AUTO_INCREMENT=11;

-- العلاقات (Foreign Keys)
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`user_id`);

ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`)  REFERENCES `users` (`user_id`)   ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`registration_id`) ON DELETE CASCADE;

COMMIT;
