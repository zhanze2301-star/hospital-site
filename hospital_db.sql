-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Янв 21 2026 г., 15:05
-- Версия сервера: 9.1.0
-- Версия PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `hospital_db`
--

-- --------------------------------------------------------

--
-- Структура таблицы `appointments`
--

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_name` varchar(150) NOT NULL,
  `patient_phone` varchar(20) DEFAULT NULL COMMENT 'Для связи',
  `doctor_id` int NOT NULL,
  `appointment_datetime` datetime NOT NULL COMMENT 'Дата и время приёма',
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('unpaid','paid') DEFAULT 'unpaid' COMMENT 'Пока наличными',
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `doctors`
--

DROP TABLE IF EXISTS `doctors`;
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `speciality_id` int NOT NULL,
  `description` text,
  `photo_url` varchar(255) DEFAULT NULL COMMENT 'Ссылка на фото',
  `rating` decimal(3,1) DEFAULT '0.0' COMMENT 'Средний рейтинг 0.0-5.0',
  PRIMARY KEY (`id`),
  KEY `speciality_id` (`speciality_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `doctors`
--

INSERT INTO `doctors` (`id`, `name`, `speciality_id`, `description`, `photo_url`, `rating`) VALUES
(1, 'Иванов Иван Иванович\r\n', 1, 'Опыт 15 лет, кандидат наук.\r\n', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ1HLt964JQLk0k-6rKs389WMjtwgYNyWggsg&s', 4.5),
(4, 'Бекташ \r\nкызы Айзада', 3, 'Стаж 11 лет', 'https://odoctor.kg/media/doctors/bektash-kyzy-aizada.webp', 8.4),
(5, 'Нурыева Дженнет Эминовна\r\n', 4, 'Стаж 9 лет', 'https://odoctor.kg/media/doctors/nurieva-jennet.webp', 9.2),
(6, 'Белекова Гулкайыр Эшбаевна\r\n', 1, 'Стаж 16 лет', 'https://odoctor.kg/media/doctors/belekova-gulkayyr_4QI4Nk4.webp', 9.9),
(7, 'Асанова Гулбайра Махматисаевна\r\n', 6, 'Стаж 31 год', 'https://odoctor.kg/media/doctors/asanova-gulbaira.webp', 10.0),
(8, 'Мамаджанова Сонунай Сраждиновна\r\n', 7, 'Невропатолог (Невролог), УЗИ-специалист\r\nСтаж 12 лет', 'https://odoctor.kg/media/doctors/mamajanova-sonunay.webp', 9.4),
(9, 'Коргонбаев Сатылган Анатаевич\r\n', 9, 'Стаж 38 лет / Врач высшей категории', 'https://odoctor.kg/media/doctors/korgonbaev-satylgan.webp', 0.0),
(10, 'Султангазиева Гулжамал Абалиевна', 2, 'Стаж 15 лет', 'https://odoctor.kg/media/doctors/sultangaziev-gulzhamal.webp', 0.0),
(11, 'Жаналиев Айбек Жолдошбаевич\r\n', 14, 'Стаж 16 лет', 'https://odoctor.kg/media/doctors/default.png', 0.0),
(12, 'Жолдошбекова \r\nАсель Улановна\r\n', 10, 'Стаж 9 лет', 'https://odoctor.kg/media/doctors/zholdoshbekova-asel_v3MXIVL.webp', 0.0),
(13, 'Мирзабековна Дилором Алдаяровна\r\n', 11, 'Стаж 1 год', 'https://odoctor.kg/media/doctors/mirzabekovna-dilorom.webp', 0.0),
(14, 'Шерматов Батырали Ташбаевич\r\n', 12, 'Стаж 16 лет', 'https://odoctor.kg/media/doctors/default.png', 0.0);

-- --------------------------------------------------------

--
-- Структура таблицы `ratings`
--

DROP TABLE IF EXISTS `ratings`;
CREATE TABLE IF NOT EXISTS `ratings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` int NOT NULL COMMENT 'Одна оценка на одну запись',
  `score` tinyint NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_id` (`appointment_id`)
) ;

-- --------------------------------------------------------

--
-- Структура таблицы `specialities`
--

DROP TABLE IF EXISTS `specialities`;
CREATE TABLE IF NOT EXISTS `specialities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Например: Кардиология',
  `description` text COMMENT 'Краткое описание с odoktor.kg',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `specialities`
--

INSERT INTO `specialities` (`id`, `name`, `description`) VALUES
(1, 'Кардиология\r\n', 'Диагностика и лечение заболеваний сердца и сосудов.\r\n'),
(2, 'Терапевт\r\n', 'Общее лечение взрослых пациентов.\r\n'),
(3, 'Лор', 'Лечит уши, горло, нос'),
(4, 'Дерматолог', 'Лечит кожу'),
(5, 'Кардиолог', 'Лечит сердце'),
(6, 'Окулист (Офтальмолог)', 'Лечит глаза'),
(7, 'Невропатолог (Невролог)', 'Работает с нервами и лечит их'),
(8, 'УЗИ-специалист', 'проводит УЗИ'),
(9, 'Хирург', 'Проводит операции'),
(10, 'Акушер-гинеколог', 'помогает при рождении детей и гинеколог'),
(11, 'Педиатр', 'Лечит детей'),
(12, 'Массажист', 'Делает оздоровительный массаж'),
(13, 'Детский массажист', 'Проводит массажи для детей'),
(14, 'Рентгенолог', 'Фотографирует рентген тела'),
(15, 'Травматолог', 'Лечит травмы без хирургического вмешательства');

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`speciality_id`) REFERENCES `specialities` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
