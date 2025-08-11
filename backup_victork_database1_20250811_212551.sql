-- MySQL dump 10.13  Distrib 8.0.42, for Linux (x86_64)
--
-- Host: mysql-victork.alwaysdata.net    Database: victork_database1
-- ------------------------------------------------------
-- Server version	5.5.5-10.11.13-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `api_daily_usage`
--

DROP TABLE IF EXISTS `api_daily_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_daily_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usage_date` date NOT NULL,
  `model` varchar(128) NOT NULL,
  `calls` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_date_model` (`usage_date`,`model`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_daily_usage`
--

LOCK TABLES `api_daily_usage` WRITE;
/*!40000 ALTER TABLE `api_daily_usage` DISABLE KEYS */;
INSERT INTO `api_daily_usage` VALUES (1,'2025-08-10','meta-llama/llama-3.3-70b-instruct:free',1),(2,'2025-08-11','meta-llama/llama-3.3-70b-instruct:free',2),(4,'2025-08-11','deepseek/deepseek-chat-v3-0324:free',11);
/*!40000 ALTER TABLE `api_daily_usage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_usage_log`
--

DROP TABLE IF EXISTS `api_usage_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_usage_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service` varchar(50) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `request_date` datetime DEFAULT current_timestamp(),
  `units_used` int(11) NOT NULL,
  `unit_type` varchar(20) NOT NULL,
  `details` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_usage_log`
--

LOCK TABLES `api_usage_log` WRITE;
/*!40000 ALTER TABLE `api_usage_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_usage_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `difficult_words`
--

DROP TABLE IF EXISTS `difficult_words`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `difficult_words` (
  `source_word` varchar(255) DEFAULT NULL,
  `target_word` varchar(255) DEFAULT NULL,
  `language` varchar(50) NOT NULL,
  `last_attempt` timestamp NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=256 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `difficult_words`
--

LOCK TABLES `difficult_words` WRITE;
/*!40000 ALTER TABLE `difficult_words` DISABLE KEYS */;
INSERT INTO `difficult_words` VALUES ('okno','window','english','2025-07-19 10:16:36',20,148,NULL),('dveře','door','english','2025-07-19 10:16:36',20,149,NULL),('stůl','table','english','2025-07-20 22:42:53',20,167,NULL),('židle','chair','english','2025-07-20 10:10:03',20,153,NULL),('okno','das Fenster','german','2025-07-20 21:10:47',20,163,NULL),('lednice','fridge','english','2025-07-20 21:10:37',20,162,NULL),('židle','chair','english','2025-07-20 13:48:58',2,157,NULL),('okno','window','english','2025-07-20 13:48:59',2,158,NULL),('stůl','table','english','2025-07-20 13:49:18',2,159,NULL),('dveře','die Tür','german','2025-07-20 21:10:48',20,164,NULL),('podlaha','der Boden','german','2025-07-20 21:10:48',20,165,NULL),('střecha','das Dach','german','2025-07-20 21:10:48',20,166,NULL),('sporák','cooker','english','2025-07-20 22:42:53',20,168,NULL),('Staroměstské náměstí','Altstädter Ring','german','2025-08-03 18:35:58',23,226,'kremlik_prag_06altstadterring'),('radnice','das Rathaus','german','2025-08-03 18:36:11',23,227,'kremlik_prag_06altstadterring'),('orloj','die astronomische Uhr','german','2025-08-03 18:36:11',23,228,'kremlik_prag_06altstadterring'),('rotunda','die Rotunde','german','2025-08-03 21:31:04',23,229,'kremlik_prag_17vysehrad'),('Pomník Jana Husa','Jan-Hus-Denkmal','german','2025-08-03 21:32:43',23,230,'kremlik_prag_09hus'),('reformátor','der Reformator','german','2025-08-03 21:32:43',23,231,'kremlik_prag_09hus'),('Obecní dům','das Gemeindehaus','german','2025-08-04 21:47:18',23,232,'kremlik_prag_01gemeindeshaus'),('secese (art nouveau)','der Jugendstil','german','2025-08-04 21:47:18',23,233,'kremlik_prag_01gemeindeshaus'),('architektura','die Architektur','german','2025-08-04 21:47:19',23,234,'kremlik_prag_01gemeindeshaus'),('Díky.','Grazie.','italian','2025-08-11 18:04:15',23,235,'kremlik_languages_italian5'),('Dobrý den.','Buongiorno.','italian','2025-08-11 18:04:19',23,236,'kremlik_languages_italian5'),('Jmenuji se.','Mi chiamo.','italian','2025-08-11 19:19:05',23,238,'kremlik_languages_italian5'),('Prosím.','Per favore.','italian','2025-08-11 19:19:07',23,239,'kremlik_languages_italian5'),('Prašná brána','der Pulverturm','german','2025-08-11 19:20:38',23,240,'kremlik_prag_02pulverturm'),('městská brána','das Stadttor','german','2025-08-11 19:20:39',23,241,'kremlik_prag_02pulverturm'),('dar','das Geschenk','german','2025-08-11 19:20:40',23,242,'kremlik_prag_02pulverturm'),('socha','die Statue','german','2025-08-11 19:20:40',23,243,'kremlik_prag_02pulverturm'),('Nashledanou.','Arrivederci.','italian','2025-08-11 19:20:56',23,244,'kremlik_languages_italian5'),('Díky.','Thanks.','english','2025-08-11 19:30:58',23,245,'kremlik_languages_english'),('Nashledanou.','Goodbye.','english','2025-08-11 19:30:59',23,246,'kremlik_languages_english'),('Jmenuji se.','Mi nombre es.','spanish','2025-08-11 20:15:35',23,253,'kremlik_languages_spanish2'),('Prosím.','Por favor.','spanish','2025-08-11 20:15:40',23,254,'kremlik_languages_spanish2'),('Prosím.','S\'il vous plaît','french','2025-08-11 20:16:07',23,255,'kremlik_languages_french');
/*!40000 ALTER TABLE `difficult_words` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `example_table`
--

DROP TABLE IF EXISTS `example_table`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `example_table` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_line` mediumtext DEFAULT NULL,
  `translated_line` mediumtext DEFAULT NULL,
  `source_lang` varchar(10) DEFAULT NULL,
  `target_lang` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `example_table`
--

LOCK TABLES `example_table` WRITE;
/*!40000 ALTER TABLE `example_table` DISABLE KEYS */;
/*!40000 ALTER TABLE `example_table` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_languages_english`
--

DROP TABLE IF EXISTS `kremlik_languages_english`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_languages_english` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_languages_english`
--

LOCK TABLES `kremlik_languages_english` WRITE;
/*!40000 ALTER TABLE `kremlik_languages_english` DISABLE KEYS */;
INSERT INTO `kremlik_languages_english` VALUES ('Díky.','Thanks.'),('Dobré odpoledne.','Good afternoon.'),('Nashledanou.','Goodbye.'),('Jmenuji se.','My name is'),('Prosím.','Please.');
/*!40000 ALTER TABLE `kremlik_languages_english` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_languages_french`
--

DROP TABLE IF EXISTS `kremlik_languages_french`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_languages_french` (
  `Czech` varchar(255) NOT NULL,
  `French` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_languages_french`
--

LOCK TABLES `kremlik_languages_french` WRITE;
/*!40000 ALTER TABLE `kremlik_languages_french` DISABLE KEYS */;
INSERT INTO `kremlik_languages_french` VALUES ('Díky.','Merci.'),('Dobrý den.','Bonjour,'),('Nashledanou.','Au revoir.'),('Jmenuji se.','Je m\'appelle.'),('Prosím.','S\'il vous plaît');
/*!40000 ALTER TABLE `kremlik_languages_french` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_languages_french3`
--

DROP TABLE IF EXISTS `kremlik_languages_french3`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_languages_french3` (
  `czech` varchar(255) NOT NULL,
  `french` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_languages_french3`
--

LOCK TABLES `kremlik_languages_french3` WRITE;
/*!40000 ALTER TABLE `kremlik_languages_french3` DISABLE KEYS */;
INSERT INTO `kremlik_languages_french3` VALUES ('Díky.','Merci.'),('Dobrý den.','Bonjour.'),('Nashledanou.','Au revoir.'),('Jmenuji se.','Mon nom est.'),('Prosím.','S\'il te plaît.');
/*!40000 ALTER TABLE `kremlik_languages_french3` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_languages_german`
--

DROP TABLE IF EXISTS `kremlik_languages_german`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_languages_german` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_languages_german`
--

LOCK TABLES `kremlik_languages_german` WRITE;
/*!40000 ALTER TABLE `kremlik_languages_german` DISABLE KEYS */;
INSERT INTO `kremlik_languages_german` VALUES ('Díky.','Danke.'),('Dobrý den.','Guten Tag'),('Nashledanou.','Auf Wiedersehen.'),('Jmenuji se.','Mein Name ist'),('Prosím.','Bitte.');
/*!40000 ALTER TABLE `kremlik_languages_german` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_languages_italian`
--

DROP TABLE IF EXISTS `kremlik_languages_italian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_languages_italian` (
  `Czech` varchar(255) NOT NULL,
  `Italian` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_languages_italian`
--

LOCK TABLES `kremlik_languages_italian` WRITE;
/*!40000 ALTER TABLE `kremlik_languages_italian` DISABLE KEYS */;
INSERT INTO `kremlik_languages_italian` VALUES ('Díky.','Grazie'),('Dobrý den.','Buongiorno'),('Nashledanou.','Arrivederci.'),('Jmenuji se.','Mi chiamo'),('Prosím.','Por favore.');
/*!40000 ALTER TABLE `kremlik_languages_italian` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_languages_italian5`
--

DROP TABLE IF EXISTS `kremlik_languages_italian5`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_languages_italian5` (
  `czech` varchar(255) NOT NULL,
  `italian` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_languages_italian5`
--

LOCK TABLES `kremlik_languages_italian5` WRITE;
/*!40000 ALTER TABLE `kremlik_languages_italian5` DISABLE KEYS */;
INSERT INTO `kremlik_languages_italian5` VALUES ('Díky.','Grazie.'),('Dobrý den.','Buongiorno.'),('Nashledanou.','Arrivederci.'),('Jmenuji se.','Mi chiamo.'),('Prosím.','Per favore.');
/*!40000 ALTER TABLE `kremlik_languages_italian5` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_languages_spanish`
--

DROP TABLE IF EXISTS `kremlik_languages_spanish`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_languages_spanish` (
  `Czech` varchar(255) NOT NULL,
  `Spanish` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_languages_spanish`
--

LOCK TABLES `kremlik_languages_spanish` WRITE;
/*!40000 ALTER TABLE `kremlik_languages_spanish` DISABLE KEYS */;
INSERT INTO `kremlik_languages_spanish` VALUES ('Díky.','Gracias.'),('Dobrý den.','Buenos días,'),('Nashledanou.','Hasta la vista'),('Jmenuji se.','Me llamo.'),('Prosím.','Per favor');
/*!40000 ALTER TABLE `kremlik_languages_spanish` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_languages_spanish2`
--

DROP TABLE IF EXISTS `kremlik_languages_spanish2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_languages_spanish2` (
  `czech` varchar(255) NOT NULL,
  `spanish` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_languages_spanish2`
--

LOCK TABLES `kremlik_languages_spanish2` WRITE;
/*!40000 ALTER TABLE `kremlik_languages_spanish2` DISABLE KEYS */;
INSERT INTO `kremlik_languages_spanish2` VALUES ('Díky.','Gracias.'),('Dobrý den.','Buen día.'),('Nashledanou.','Adiós.'),('Jmenuji se.','Mi nombre es.'),('Prosím.','Por favor.'),('Jak se máte?','¿Cómo estás?'),('Miluji vás.','Te amo.');
/*!40000 ALTER TABLE `kremlik_languages_spanish2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_01gemeindeshaus`
--

DROP TABLE IF EXISTS `kremlik_prag_01gemeindeshaus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_01gemeindeshaus` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_01gemeindeshaus`
--

LOCK TABLES `kremlik_prag_01gemeindeshaus` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_01gemeindeshaus` DISABLE KEYS */;
INSERT INTO `kremlik_prag_01gemeindeshaus` VALUES ('Obecní dům','das Gemeindehaus'),('secese (art nouveau)','der Jugendstil'),('architektura','die Architektur'),('koncertní sál','der Konzertsaal'),('Smetanova síň','der Smetana-Saal'),('vybavení','die Ausrüstung'),('výtah','der Aufzug'),('klimatizace','die Klimaanlage'),('linoleum','das Linoleum'),('varhany','die Orgel'),('socha','die Statue'),('mozaika','das Mosaik'),('umělec','der Künstler'),('české dějiny','die tschechische Geschichte'),('národní cítění','das nationale Gefühl'),('Po levé straně vidíte slavný Obecní dům.','Auf der linken Seite sehen Sie das berühmte Gemeindehaus.'),('Je to jeden z nejkrásnějších příkladů secese v Praze.','Es ist eines der schönsten Beispiele des Jugendstils in Prag.'),('Zde byla 28. října 1918 vyhlášena nezávislá Československá republika.','Hier wurde am 28. Oktober 1918 die unabhängige Tschechoslowakische Republik ausgerufen.'),('Smetanova síň je známá svou vynikající akustikou.','Der Smetana-Saal ist für seine hervorragende Akustik bekannt.'),('Fasáda je ozdobena mozaikami a sochami českých umělců.','Die Fassade ist mit Mosaiken und Statuen tschechischer Künstler geschmückt.'),('Dnes se zde konají koncerty a významné akce.','Heute finden hier Konzerte und wichtige Veranstaltungen statt.');
/*!40000 ALTER TABLE `kremlik_prag_01gemeindeshaus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_02pulverturm`
--

DROP TABLE IF EXISTS `kremlik_prag_02pulverturm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_02pulverturm` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_02pulverturm`
--

LOCK TABLES `kremlik_prag_02pulverturm` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_02pulverturm` DISABLE KEYS */;
INSERT INTO `kremlik_prag_02pulverturm` VALUES ('Prašná brána','der Pulverturm'),('gotický styl','der gotische Stil'),('městská brána','das Stadttor'),('Matěj Rejsek','Matěj Rejsek'),('dar','das Geschenk'),('král Vladislav II.','König Vladislav II.'),('socha','die Statue'),('střelný prach','das Schießpulver'),('historické centrum','das historische Zentrum'),('král Přemysl Otakar II.','König Přemysl Otakar II.'),('král Karel IV.','König Karl IV.'),('Jiří z Poděbrad','Georg von Poděbrad'),('významná památka','das bedeutende Denkmal'),('vstup do Starého Města','der Eingang zur Altstadt'),('Prašná brána je jednou z nejvýznamnějších gotických památek v Praze.','Der Pulverturm ist eines der bedeutendsten gotischen Bauwerke in Prag.'),('Byla postavena v roce 1475 jako náhrada za původní městskou bránu.','Er wurde im Jahr 1475 als Ersatz für ein ursprüngliches Stadttor erbaut.'),('Autorem stavby je Matěj Rejsek z Prostějova.','Der Bau stammt von Matěj Rejsek aus Prostějov.'),('Název ‚Prašná brána‘ získala ve 17. století, kdy zde bylo skladováno střelné prach.','Den Namen ‚Pulverturm‘ erhielt sie im 17. Jahrhundert, als hier Schießpulver gelagert wurde.'),('Brána je zdobena sochami českých králů, například Karla IV. a Jiřího z Poděbrad.','Das Tor ist mit Statuen böhmischer Könige verziert, wie Karl IV. und Georg von Poděbrad.'),('Je to oblíbený cíl turistů a vstupní brána do Starého Města.','Sie ist ein beliebtes Ziel für Touristen und der Eingang zur Altstadt.');
/*!40000 ALTER TABLE `kremlik_prag_02pulverturm` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_03schwarzemutter`
--

DROP TABLE IF EXISTS `kremlik_prag_03schwarzemutter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_03schwarzemutter` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_03schwarzemutter`
--

LOCK TABLES `kremlik_prag_03schwarzemutter` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_03schwarzemutter` DISABLE KEYS */;
INSERT INTO `kremlik_prag_03schwarzemutter` VALUES ('Dům U Černé Matky Boží','Haus Zur Schwarzen Mutter Gottes'),('kubismus','der Kubismus'),('architektura','die Architektur'),('Josef Gočár','Josef Gočár'),('ocelobetonová konstrukce','die Stahlbetonkonstruktion'),('interiér','das Interieur'),('nábytek','die Möbel'),('socha černé madony','die Statue der schwarzen Madonna'),('rohová socha','die Eckstatue'),('baroko','das Barock'),('výjimečný příklad','das herausragende Beispiel'),('geometrické tvary','die geometrischen Formen'),('muzeum','das Museum'),('historické centrum','das historische Zentrum'),('průvodce','der Reiseführer'),('Dům U Černé Matky Boží je slavná kubistická budova v Praze.','Das Haus Zur Schwarzen Mutter Gottes ist ein berühmtes kubistisches Gebäude in Prag.'),('Budova byla postavena v letech 1911–1912 podle návrhu Josefa Gočára.','Das Gebäude wurde in den Jahren 1911–1912 nach den Plänen von Josef Gočár errichtet.'),('Je to nejstarší kubistický dům v Praze.','Es ist das älteste kubistische Haus in Prag.'),('Interiér obsahuje původní kubistický nábytek a dekorace.','Das Interieur enthält originale kubistische Möbel und Dekorationen.'),('Na rohu domu je barokní socha Černé madony...','An der Ecke des Hauses befindet sich eine barocke Statue der Schwarzen Madonna.'),('Dnes je v domě muzeum českého kubismu.','Heute befindet sich in dem Haus das Museum des tschechischen Kubismus.'),('dd','dd');
/*!40000 ALTER TABLE `kremlik_prag_03schwarzemutter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_04jacob`
--

DROP TABLE IF EXISTS `kremlik_prag_04jacob`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_04jacob` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_04jacob`
--

LOCK TABLES `kremlik_prag_04jacob` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_04jacob` DISABLE KEYS */;
INSERT INTO `kremlik_prag_04jacob` VALUES ('Bazilika svatého Jakuba','Jakobskirche'),('baroko','der Barock'),('kostel','die Kirche'),('klášter','das Kloster'),('varhany','die Orgel'),('socha sv. Jakuba','die Statue des heiligen Jakobus'),('oltář','der Altar'),('hrobka','die Gruft'),('stropní freska','das Deckenfresko'),('kazatelna','die Kanzel'),('gotický základ','das gotische Fundament'),('renesance','die Renaissance'),('restaurování','die Restaurierung'),('hudební koncert','das Musikkonzert'),('turistická atrakce','die Touristenattraktion'),('Bazilika sv. Jakuba je známá svou bohatou barokní výzdobou.','Die Jakobskirche ist für ihre reiche barocke Ausstattung bekannt.'),('Byla postavena na gotických základech a později přestavěna v barokním stylu.','Sie wurde auf gotischen Fundamenten errichtet und später im Barockstil umgebaut.'),('V kostele se nacházejí největší varhany v Praze.','In der Kirche befindet sich die größte Orgel in Prag.'),('Stropní fresky a sochy vytvářejí působivý interiér.','Die Deckenfresken und Statuen schaffen ein beeindruckendes Interieur.'),('Nachází se poblíž Ungeltu a Staroměstského náměstí.','Sie befindet sich in der Nähe des Ungelt und des Altstädter Rings.');
/*!40000 ALTER TABLE `kremlik_prag_04jacob` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_05ungelt`
--

DROP TABLE IF EXISTS `kremlik_prag_05ungelt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_05ungelt` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_05ungelt`
--

LOCK TABLES `kremlik_prag_05ungelt` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_05ungelt` DISABLE KEYS */;
INSERT INTO `kremlik_prag_05ungelt` VALUES ('Ungelt','Ungelt'),('celní dvůr','der Zollhof'),('středověk','das Mittelalter'),('obchodníci','die Händler'),('městská brána','das Stadttor'),('dům','das Haus'),('sklad','das Lager'),('historické centrum','das historische Zentrum'),('arcibiskupský palác','der Erzbischöfliche Palast'),('restaurace','das Restaurant'),('kavárna','das Café'),('nádvoří','der Innenhof'),('románská kaple','die romanische Kapelle'),('památník','das Denkmal'),('turistické místo','der Touristenort'),('Ungelt je historický celní dvůr v srdci Prahy.','Ungelt ist ein historischer Zollhof im Herzen von Prag.'),('Ve středověku zde museli obchodníci platit clo za své zboží.','Im Mittelalter mussten Händler hier Zoll für ihre Waren bezahlen.'),('Nádvoří je obklopeno domy s gotickými a renesančními prvky.','Der Innenhof ist von Häusern mit gotischen und Renaissance-Elementen umgeben.'),('Dnes zde najdete restaurace, kavárny a obchody.','Heute finden Sie hier Restaurants, Cafés und Geschäfte.'),('Ungelt je oblíbeným turistickým místem nedaleko Staroměstského náměstí.','Ungelt ist ein beliebtes Touristenziel in der Nähe des Altstädter Rings.'),('Románská kaple v areálu je zajímavou historickou památkou.','Die romanische Kapelle im Areal ist ein interessantes historisches Denkmal.');
/*!40000 ALTER TABLE `kremlik_prag_05ungelt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_06altstadterring`
--

DROP TABLE IF EXISTS `kremlik_prag_06altstadterring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_06altstadterring` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_06altstadterring`
--

LOCK TABLES `kremlik_prag_06altstadterring` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_06altstadterring` DISABLE KEYS */;
INSERT INTO `kremlik_prag_06altstadterring` VALUES ('Staroměstské náměstí','Altstädter Ring'),('radnice','das Rathaus'),('orloj','die astronomische Uhr'),('Jan Hus','Jan Hus'),('pomník','das Denkmal'),('kostel sv. Mikuláše','Nikolaikirche'),('kostel Panny Marie před Týnem','Kirche Maria vor dem Tein'),('gotika','die Gotik'),('baroko','der Barock'),('tržiště','der Marktplatz'),('měšťanský dům','das Bürgerhaus'),('náměstí','der Platz'),('turistický cíl','das Touristenziel'),('památná událost','das historische Ereignis'),('slavnost','die Feier'),('Staroměstské náměstí je historickým centrem Prahy.','Der Altstädter Ring ist das historische Zentrum von Prag.'),('Na náměstí stojí Staroměstská radnice s orlojem.','Am Platz steht das Altstädter Rathaus mit der astronomischen Uhr.'),('Uprostřed náměstí se nachází pomník Jana Husa.','In der Mitte des Platzes befindet sich das Jan-Hus-Denkmal.'),('Kostel Panny Marie před Týnem je dominantou východní strany náměstí.','Die Kirche Maria vor dem Tein ist die Dominante der Ostseite des Platzes.'),('Náměstí je oblíbeným místem pro slavnosti a trhy.','Der Platz ist ein beliebter Ort für Feiern und Märkte.'),('Každý den zde přicházejí turisté, aby obdivovali orloj.','Täglich kommen Touristen hierher, um die astronomische Uhr zu bewundern.');
/*!40000 ALTER TABLE `kremlik_prag_06altstadterring` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_07teynkirche`
--

DROP TABLE IF EXISTS `kremlik_prag_07teynkirche`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_07teynkirche` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_07teynkirche`
--

LOCK TABLES `kremlik_prag_07teynkirche` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_07teynkirche` DISABLE KEYS */;
INSERT INTO `kremlik_prag_07teynkirche` VALUES ('Kostel Panny Marie před Týnem','Kirche Maria vor dem Tein'),('gotika','die Gotik'),('věž','der Turm'),('chrám','der Dom'),('oltář','der Altar'),('socha Panny Marie','die Statue der Jungfrau Maria'),('vitráž','das Buntglasfenster'),('klenba','das Gewölbe'),('kazatelna','die Kanzel'),('renovace','die Renovierung'),('náměstí','der Platz'),('vstupní portál','das Eingangsportal'),('turistická památka','die Touristenattraktion'),('bohoslužba','der Gottesdienst'),('historie','die Geschichte'),('Kostel Panny Marie před Týnem je dominantou Staroměstského náměstí.','Die Kirche Maria vor dem Tein ist die Dominante des Altstädter Rings.'),('Byl postaven ve 14. století v gotickém stylu.','Er wurde im 14. Jahrhundert im gotischen Stil erbaut.'),('Je známý svými charakteristickými věžemi.','Er ist bekannt für seine charakteristischen Türme.'),('Interiér chrámu je bohatě zdoben oltáři a vitrážemi.','Das Kircheninnere ist reich mit Altären und Buntglasfenstern verziert.'),('Kostel je aktivním místem bohoslužeb a koncertů.','Die Kirche ist ein aktiver Ort für Gottesdienste und Konzerte.'),('Z jeho věží je krásný výhled na Prahu.','Von seinen Türmen hat man einen wunderschönen Blick auf Prag.');
/*!40000 ALTER TABLE `kremlik_prag_07teynkirche` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_08nikolaikirche`
--

DROP TABLE IF EXISTS `kremlik_prag_08nikolaikirche`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_08nikolaikirche` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_08nikolaikirche`
--

LOCK TABLES `kremlik_prag_08nikolaikirche` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_08nikolaikirche` DISABLE KEYS */;
INSERT INTO `kremlik_prag_08nikolaikirche` VALUES ('Kostel sv. Mikuláše','Nikolaikirche'),('baroko','der Barock'),('architekt','der Architekt'),('klenba','das Gewölbe'),('freska','das Fresko'),('varhany','die Orgel'),('socha sv. Mikuláše','die Statue des heiligen Nikolaus'),('kupole','die Kuppel'),('façáda','die Fassade'),('náměstí','der Platz'),('historická budova','das historische Gebäude'),('svatba','die Hochzeit'),('koncert','das Konzert'),('turistická atrakce','die Touristenattraktion'),('restaurace','die Restaurierung'),('Kostel sv. Mikuláše je významná barokní památka na Staroměstském náměstí.','Die Nikolaikirche ist ein bedeutendes barockes Denkmal am Altstädter Ring.'),('Byl postaven v 18. století podle plánů významných architektů.','Er wurde im 18. Jahrhundert nach Plänen bedeutender Architekten errichtet.'),('Interiér je bohatě zdoben freskami a sochami.','Das Innere ist reich mit Fresken und Statuen geschmückt.'),('Kupole kostela nabízí skvělou akustiku pro koncerty.','Die Kuppel der Kirche bietet eine hervorragende Akustik für Konzerte.'),('Kostel je populární místo pro svatby a kulturní akce.','Die Kirche ist ein beliebter Ort für Hochzeiten und kulturelle Veranstaltungen.'),('Nachází se přímo naproti pomníku Jana Husa.','Sie befindet sich direkt gegenüber dem Jan-Hus-Denkmal.');
/*!40000 ALTER TABLE `kremlik_prag_08nikolaikirche` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_09hus`
--

DROP TABLE IF EXISTS `kremlik_prag_09hus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_09hus` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_09hus`
--

LOCK TABLES `kremlik_prag_09hus` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_09hus` DISABLE KEYS */;
INSERT INTO `kremlik_prag_09hus` VALUES ('Pomník Jana Husa','Jan-Hus-Denkmal'),('reformátor','der Reformator'),('upálení','die Verbrennung'),('kostnice','das Beinhaus'),('husité','die Hussiten'),('socha','die Statue'),('bronz','die Bronze'),('umělec','der Künstler'),('symbol svobody','das Freiheitsymbol'),('národní hrdina','der Nationalheld'),('historie','die Geschichte'),('památka','das Denkmal'),('odhalení','die Enthüllung'),('výročí','das Jubiläum'),('staroměstské náměstí','der Altstädter Ring'),('Pomník Jana Husa se nachází uprostřed Staroměstského náměstí.','Das Jan-Hus-Denkmal befindet sich in der Mitte des Altstädter Rings.'),('Byl odhalen v roce 1915 u příležitosti 500. výročí Husovy smrti.','Es wurde 1915 anlässlich des 500. Todestages von Hus enthüllt.'),('Jan Hus byl český reformátor a národní hrdina.','Jan Hus war ein tschechischer Reformator und Nationalheld.'),('Pomník je symbolem boje za pravdu a svobodu.','Das Denkmal ist ein Symbol des Kampfes für Wahrheit und Freiheit.'),('Autorem pomníku je sochař Ladislav Šaloun.','Der Bildhauer Ladislav Šaloun ist der Autor des Denkmals.'),('Každý den pomník obdivují stovky turistů.','Täglich bewundern Hunderte von Touristen das Denkmal.');
/*!40000 ALTER TABLE `kremlik_prag_09hus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_10karlsbrucke`
--

DROP TABLE IF EXISTS `kremlik_prag_10karlsbrucke`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_10karlsbrucke` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_10karlsbrucke`
--

LOCK TABLES `kremlik_prag_10karlsbrucke` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_10karlsbrucke` DISABLE KEYS */;
INSERT INTO `kremlik_prag_10karlsbrucke` VALUES ('Karlův most','Karlsbrücke'),('Vltava','die Moldau'),('socha','die Statue'),('mostní věž','der Brückenturm'),('oblouk','der Bogen'),('pískovec','der Sandstein'),('středověk','das Mittelalter'),('stavitel','der Baumeister'),('král Karel IV.','König Karl IV.'),('pověst','die Legende'),('barokní sochy','die barocken Statuen'),('chodník','der Gehweg'),('umělec','der Künstler'),('hudebník','der Musiker'),('turistická atrakce','die Touristenattraktion'),('Karlův most spojuje Staré Město s Malou Stranou.','Die Karlsbrücke verbindet die Altstadt mit der Kleinseite.'),('Byl postaven ve 14. století za vlády Karla IV.','Sie wurde im 14. Jahrhundert während der Herrschaft von Karl IV. erbaut.'),('Most je vyzdoben třiceti barokními sochami.','Die Brücke ist mit dreißig barocken Statuen geschmückt.'),('Z mostu je krásný výhled na Pražský hrad.','Von der Brücke hat man einen schönen Blick auf die Prager Burg.'),('Karlův most je oblíbeným místem pro umělce a hudebníky.','Die Karlsbrücke ist ein beliebter Ort für Künstler und Musiker.'),('Most je jednou z nejnavštěvovanějších památek v Praze.','Die Brücke ist eines der meistbesuchten Denkmäler in Prag.');
/*!40000 ALTER TABLE `kremlik_prag_10karlsbrucke` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_11pragerburg`
--

DROP TABLE IF EXISTS `kremlik_prag_11pragerburg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_11pragerburg` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_11pragerburg`
--

LOCK TABLES `kremlik_prag_11pragerburg` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_11pragerburg` DISABLE KEYS */;
INSERT INTO `kremlik_prag_11pragerburg` VALUES ('Pražský hrad','Prager Burg'),('hrad','die Burg'),('katedrála sv. Víta','der Veitsdom'),('bazilika sv. Jiří','die Georgsbasilika'),('zlatá ulička','das Goldene Gässchen'),('královský palác','der Königspalast'),('korunovační klenoty','die Kronjuwelen'),('stráž','die Wache'),('nádvoří','der Hof'),('kaple','die Kapelle'),('věž','der Turm'),('zahrady','die Gärten'),('románský sloh','der romanische Stil'),('gotický sloh','der gotische Stil'),('turistický komplex','das touristische Areal'),('Pražský hrad je největší hradní komplex na světě.','Die Prager Burg ist der größte Burgenkomplex der Welt.'),('Uvnitř areálu se nachází katedrála sv. Víta a bazilika sv. Jiří.','Innerhalb des Areals befinden sich der Veitsdom und die Georgsbasilika.'),('Zlatá ulička je známá svými malými barevnými domky.','Das Goldene Gässchen ist bekannt für seine kleinen bunten Häuser.'),('Pražský hrad je sídlem prezidenta České republiky.','Die Prager Burg ist der Sitz des Präsidenten der Tschechischen Republik.'),('Každou hodinu zde probíhá výměna stráží.','Jede Stunde findet hier die Wachablösung statt.'),('Z hradu je nádherný výhled na celé město.','Von der Burg hat man einen wunderschönen Blick auf die ganze Stadt.');
/*!40000 ALTER TABLE `kremlik_prag_11pragerburg` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_12veitsdom`
--

DROP TABLE IF EXISTS `kremlik_prag_12veitsdom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_12veitsdom` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_12veitsdom`
--

LOCK TABLES `kremlik_prag_12veitsdom` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_12veitsdom` DISABLE KEYS */;
INSERT INTO `kremlik_prag_12veitsdom` VALUES ('Katedrála sv. Víta','Veitsdom'),('gotika','die Gotik'),('chrám','der Dom'),('vysoká věž','der hohe Turm'),('světec','der Heilige'),('vitráž','das Buntglasfenster'),('hrobka','die Gruft'),('kaple sv. Václava','die Wenzelskapelle'),('socha','die Statue'),('klenba','das Gewölbe'),('portál','das Portal'),('zvon','die Glocke'),('Svatováclavská koruna','die Wenzelskrone'),('mše','die Messe'),('turistická památka','die Touristenattraktion'),('Katedrála sv. Víta je nejvýznamnější gotická stavba v Praze.','Der Veitsdom ist das bedeutendste gotische Bauwerk in Prag.'),('Stavba začala ve 14. století za vlády Karla IV.','Der Bau begann im 14. Jahrhundert während der Herrschaft von Karl IV.'),('V katedrále jsou uloženy české korunovační klenoty.','In der Kathedrale sind die böhmischen Kronjuwelen aufbewahrt.'),('Kaple sv. Václava je jedním z nejcennějších míst v chrámu.','Die Wenzelskapelle ist einer der wertvollsten Orte im Dom.'),('Vysoká věž nabízí nádherný výhled na Prahu.','Der hohe Turm bietet einen herrlichen Blick auf Prag.'),('Katedrála je místem významných církevních obřadů a slavností.','Die Kathedrale ist ein Ort wichtiger kirchlicher Zeremonien und Feste.');
/*!40000 ALTER TABLE `kremlik_prag_12veitsdom` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_13georgsbasilika`
--

DROP TABLE IF EXISTS `kremlik_prag_13georgsbasilika`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_13georgsbasilika` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_13georgsbasilika`
--

LOCK TABLES `kremlik_prag_13georgsbasilika` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_13georgsbasilika` DISABLE KEYS */;
INSERT INTO `kremlik_prag_13georgsbasilika` VALUES ('Bazilika sv. Jiří','Georgsbasilika'),('románský styl','der romanische Stil'),('klášter','das Kloster'),('krypta','die Krypta'),('nádvoří','der Hof'),('kaple','die Kapelle'),('malba','das Gemälde'),('barokní úpravy','die barocke Umgestaltung'),('socha','die Statue'),('oltář','der Altar'),('Bazilika sv. Jiří je nejstarší dochovaná bazilika v Praze.','Die Georgsbasilika ist die älteste erhaltene Basilika in Prag.'),('Byla postavena v románském stylu.','Sie wurde im romanischen Stil erbaut.'),('Uvnitř se nachází krásné fresky a malby.','Im Inneren befinden sich schöne Fresken und Gemälde.'),('Součástí baziliky je i krypta s hrobkami.','Zur Basilika gehört auch eine Krypta mit Gräbern.');
/*!40000 ALTER TABLE `kremlik_prag_13georgsbasilika` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_14goldenesgasschen`
--

DROP TABLE IF EXISTS `kremlik_prag_14goldenesgasschen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_14goldenesgasschen` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_14goldenesgasschen`
--

LOCK TABLES `kremlik_prag_14goldenesgasschen` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_14goldenesgasschen` DISABLE KEYS */;
INSERT INTO `kremlik_prag_14goldenesgasschen` VALUES ('Zlatá ulička','Goldenes Gässchen'),('domek','das Häuschen'),('alchymista','der Alchemist'),('středověk','das Mittelalter'),('hradby','die Stadtmauer'),('řemeslník','der Handwerker'),('legendy','die Legenden'),('turistická atrakce','die Touristenattraktion'),('Zlatá ulička je známá svými malými barevnými domky.','Das Goldene Gässchen ist bekannt für seine kleinen bunten Häuser.'),('Ve středověku zde žili střelci a řemeslníci.','Im Mittelalter lebten hier Schützen und Handwerker.'),('Dnes jsou domky přístupné turistům jako malé muzea.','Heute sind die Häuschen als kleine Museen für Touristen zugänglich.');
/*!40000 ALTER TABLE `kremlik_prag_14goldenesgasschen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_15strahov`
--

DROP TABLE IF EXISTS `kremlik_prag_15strahov`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_15strahov` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_15strahov`
--

LOCK TABLES `kremlik_prag_15strahov` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_15strahov` DISABLE KEYS */;
INSERT INTO `kremlik_prag_15strahov` VALUES ('Strahovský klášter','Strahov-Kloster'),('premonstráti','die Prämonstratenser'),('knihovna','die Bibliothek'),('sál','der Saal'),('malba','das Gemälde'),('baroko','der Barock'),('vzácné knihy','die wertvollen Bücher'),('archiv','das Archiv'),('Strahovský klášter je známý svou nádhernou knihovnou.','Das Strahov-Kloster ist bekannt für seine wunderschöne Bibliothek.'),('Klášter byl založen v 12. století.','Das Kloster wurde im 12. Jahrhundert gegründet.'),('Knihovna obsahuje tisíce vzácných rukopisů a knih.','Die Bibliothek enthält Tausende von wertvollen Handschriften und Büchern.');
/*!40000 ALTER TABLE `kremlik_prag_15strahov` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_16petrin`
--

DROP TABLE IF EXISTS `kremlik_prag_16petrin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_16petrin` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_16petrin`
--

LOCK TABLES `kremlik_prag_16petrin` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_16petrin` DISABLE KEYS */;
INSERT INTO `kremlik_prag_16petrin` VALUES ('Petřínská rozhledna','Petřín-Aussichtsturm'),('kopce','der Hügel'),('lanovka','die Standseilbahn'),('vyhlídka','die Aussichtsplattform'),('ocelová konstrukce','die Stahlkonstruktion'),('výhled','die Aussicht'),('turisté','die Touristen'),('park','der Park'),('Petřínská rozhledna připomíná malou Eiffelovu věž.','Der Petřín-Aussichtsturm erinnert an einen kleinen Eiffelturm.'),('Z rozhledny je nádherný výhled na celé město.','Vom Aussichtsturm hat man einen wunderschönen Blick auf die ganze Stadt.'),('Na kopec se lze dostat lanovkou.','Auf den Hügel kann man mit der Standseilbahn fahren.');
/*!40000 ALTER TABLE `kremlik_prag_16petrin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_17vysehrad`
--

DROP TABLE IF EXISTS `kremlik_prag_17vysehrad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_17vysehrad` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_17vysehrad`
--

LOCK TABLES `kremlik_prag_17vysehrad` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_17vysehrad` DISABLE KEYS */;
INSERT INTO `kremlik_prag_17vysehrad` VALUES ('Vyšehrad','Vyšehrad'),('pevnost','die Festung'),('rotunda','die Rotunde'),('bazilika sv. Petra a Pavla','die Basilika St. Peter und Paul'),('hřbitov Slavín','der Friedhof Slavín'),('hradby','die Stadtmauer'),('park','der Park'),('historie','die Geschichte'),('Vyšehrad je historická pevnost nad Vltavou.','Vyšehrad ist eine historische Festung über der Moldau.'),('Na Vyšehradě se nachází hřbitov Slavín s hroby významných osobností.','Auf dem Vyšehrad befindet sich der Friedhof Slavín mit Gräbern bedeutender Persönlichkeiten.'),('Z hradeb je krásný výhled na Prahu.','Von den Mauern hat man einen wunderschönen Blick auf Prag.');
/*!40000 ALTER TABLE `kremlik_prag_17vysehrad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_18theater`
--

DROP TABLE IF EXISTS `kremlik_prag_18theater`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_18theater` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_18theater`
--

LOCK TABLES `kremlik_prag_18theater` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_18theater` DISABLE KEYS */;
INSERT INTO `kremlik_prag_18theater` VALUES ('Národní divadlo','Nationaltheater'),('scéna','die Bühne'),('opera','die Oper'),('balet','das Ballett'),('architekt','der Architekt'),('socha','die Statue'),('zlatá střecha','das goldene Dach'),('premiéra','die Premiere'),('Národní divadlo je symbolem české kultury a umění.','Das Nationaltheater ist ein Symbol der tschechischen Kultur und Kunst.'),('Bylo otevřeno v roce 1881.','Es wurde im Jahr 1881 eröffnet.'),('V divadle se hrají opery, balety i činohry.','Im Theater werden Opern, Ballette und Schauspielstücke aufgeführt.');
/*!40000 ALTER TABLE `kremlik_prag_18theater` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_19palladium`
--

DROP TABLE IF EXISTS `kremlik_prag_19palladium`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_19palladium` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_19palladium`
--

LOCK TABLES `kremlik_prag_19palladium` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_19palladium` DISABLE KEYS */;
INSERT INTO `kremlik_prag_19palladium` VALUES ('Palladium','Palladium'),('ikona','die Ikone'),('ochrana','der Schutz'),('poutní místo','der Wallfahrtsort'),('kostel','die Kirche'),('historie','die Geschichte'),('reliéf','das Relief'),('tradice','die Tradition'),('Palladium Panny Marie je považováno za ochránkyni Prahy.','Das Palladium der Jungfrau Maria wird als Beschützerin von Prag angesehen.'),('Je uloženo v kostele Panny Marie před Týnem.','Es wird in der Kirche Maria vor dem Tein aufbewahrt.'),('Každoročně přitahuje mnoho poutníků.','Jährlich zieht es viele Pilger an.');
/*!40000 ALTER TABLE `kremlik_prag_19palladium` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_20wenzelsplatz`
--

DROP TABLE IF EXISTS `kremlik_prag_20wenzelsplatz`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_20wenzelsplatz` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_20wenzelsplatz`
--

LOCK TABLES `kremlik_prag_20wenzelsplatz` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_20wenzelsplatz` DISABLE KEYS */;
INSERT INTO `kremlik_prag_20wenzelsplatz` VALUES ('Václavské náměstí','Wenzelsplatz'),('socha sv. Václava','die Statue des heiligen Wenzel'),('muzeum','das Museum'),('demonstrace','die Demonstration'),('obchodní centrum','das Einkaufszentrum'),('hotel','das Hotel'),('restaurace','das Restaurant'),('metro','die U-Bahn'),('Václavské náměstí je hlavní pražské náměstí a centrum obchodu.','Der Wenzelsplatz ist der Hauptplatz Prags und ein Zentrum des Handels.'),('Na horním konci náměstí stojí socha sv. Václava.','Am oberen Ende des Platzes steht die Statue des heiligen Wenzel.'),('Náměstí je místem důležitých historických událostí a demonstrací.','Der Platz ist ein Ort wichtiger historischer Ereignisse und Demonstrationen.');
/*!40000 ALTER TABLE `kremlik_prag_20wenzelsplatz` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_21karlsplatz`
--

DROP TABLE IF EXISTS `kremlik_prag_21karlsplatz`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_21karlsplatz` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_21karlsplatz`
--

LOCK TABLES `kremlik_prag_21karlsplatz` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_21karlsplatz` DISABLE KEYS */;
INSERT INTO `kremlik_prag_21karlsplatz` VALUES ('náměstí','der Platz'),('fontána','der Brunnen'),('socha','die Statue'),('kostel','die Kirche'),('radnice','das Rathaus'),('historie','die Geschichte'),('památka','das Denkmal'),('turista','der Tourist'),('prohlídka','die Besichtigung'),('výhled','die Aussicht'),('Na náměstí stojí historická radnice.','Auf dem Platz steht das historische Rathaus.'),('Uprostřed náměstí je krásná fontána.','In der Mitte des Platzes befindet sich ein schöner Brunnen.'),('Socha připomíná významnou událost v historii města.','Die Statue erinnert an ein wichtiges Ereignis in der Stadtgeschichte.'),('Turisté zde často zahajují svou prohlídku města.','Touristen beginnen hier oft ihre Stadtbesichtigung.'),('Z radniční věže je nádherný výhled na celé okolí.','Von dem Rathausturm hat man einen herrlichen Blick auf die Umgebung.');
/*!40000 ALTER TABLE `kremlik_prag_21karlsplatz` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_22`
--

DROP TABLE IF EXISTS `kremlik_prag_22`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_22` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_22`
--

LOCK TABLES `kremlik_prag_22` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_22` DISABLE KEYS */;
INSERT INTO `kremlik_prag_22` VALUES ('kaple','die Kapelle'),('baroko','der Barock'),('oltář','der Altar'),('socha anděla','die Engelstatue'),('vitráž','das Buntglasfenster'),('svíčka','die Kerze'),('lavice','die Bank'),('kazatelna','die Kanzel'),('nástropní freska','das Deckengemälde'),('poutní místo','der Wallfahrtsort'),('Kaple sv. Anny je barokní stavba z 18. století.','Die Anna-Kapelle ist ein barockes Bauwerk aus dem 18. Jahrhundert.'),('Uvnitř kaple najdete nádherný oltář.','Im Inneren der Kapelle finden Sie einen wunderschönen Altar.'),('Strop je zdoben freskami s biblickými motivy.','Die Decke ist mit Fresken biblischer Motive verziert.'),('Mnoho poutníků sem přichází zapálit svíčku.','Viele Pilger kommen hierher, um eine Kerze anzuzünden.'),('Kaple je součástí historického komplexu.','Die Kapelle ist Teil des historischen Komplexes.');
/*!40000 ALTER TABLE `kremlik_prag_22` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_23altepalast`
--

DROP TABLE IF EXISTS `kremlik_prag_23altepalast`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_23altepalast` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_23altepalast`
--

LOCK TABLES `kremlik_prag_23altepalast` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_23altepalast` DISABLE KEYS */;
INSERT INTO `kremlik_prag_23altepalast` VALUES ('palác','der Palast'),('gotika','die Gotik'),('trůnní sál','der Thronsaal'),('korunovační klenoty','die Kronjuwelen'),('zbrojnice','die Waffenkammer'),('král','der König'),('královna','die Königin'),('nádvoří','der Hof'),('věž','der Turm'),('rekonstrukce','die Rekonstruktion'),('Starý palác byl sídlem českých králů.','Der Alte Palast war der Sitz der böhmischen Könige.'),('V trůnním sále se konaly slavnostní ceremonie.','Im Thronsaal fanden feierliche Zeremonien statt.'),('Palác uchovává české korunovační klenoty.','Der Palast bewahrt die böhmischen Kronjuwelen auf.'),('Návštěvníci mohou vidět historickou zbrojnici.','Besucher können die historische Waffenkammer besichtigen.'),('Budova byla v minulém století zrekonstruována.','Das Gebäude wurde im letzten Jahrhundert renoviert.');
/*!40000 ALTER TABLE `kremlik_prag_23altepalast` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_24erzbischof`
--

DROP TABLE IF EXISTS `kremlik_prag_24erzbischof`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_24erzbischof` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_24erzbischof`
--

LOCK TABLES `kremlik_prag_24erzbischof` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_24erzbischof` DISABLE KEYS */;
INSERT INTO `kremlik_prag_24erzbischof` VALUES ('arcibiskup','der Erzbischof'),('palác','der Palast'),('barokní fasáda','die barocke Fassade'),('okno','das Fenster'),('kaple','die Kapelle'),('socha svatého','die Heiligenstatue'),('zahrada','der Garten'),('brána','das Tor'),('nádvoří','der Innenhof'),('úřad','das Amt'),('Arcibiskupský palác je významná barokní budova.','Der Erzbischöfliche Palast ist ein bedeutendes barockes Gebäude.'),('Fasáda paláce je bohatě zdobena.','Die Fassade des Palastes ist reich verziert.'),('Uvnitř paláce se nachází soukromá kaple.','Im Inneren des Palastes befindet sich eine private Kapelle.'),('Palác je obklopen krásnou zahradou.','Der Palast ist von einem schönen Garten umgeben.'),('Brána paláce je monumentální.','Das Tor des Palastes ist monumental.');
/*!40000 ALTER TABLE `kremlik_prag_24erzbischof` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_25annalustschloss`
--

DROP TABLE IF EXISTS `kremlik_prag_25annalustschloss`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_25annalustschloss` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_25annalustschloss`
--

LOCK TABLES `kremlik_prag_25annalustschloss` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_25annalustschloss` DISABLE KEYS */;
INSERT INTO `kremlik_prag_25annalustschloss` VALUES ('letohrádek','das Lustschloss'),('renesance','die Renaissance'),('královna','die Königin'),('socha','die Statue'),('kašna','der Springbrunnen'),('zahrada','der Garten'),('portikus','der Portikus'),('sloup','die Säule'),('výstavba','der Bau'),('památka','das Denkmal'),('Letohrádek královny Anny je renesanční stavba.','Das Lustschloss der Königin Anna ist ein Renaissancebauwerk.'),('Před letohrádkem stojí slavná zpívající fontána.','Vor dem Lustschloss steht der berühmte singende Brunnen.'),('Zahrady kolem letohrádku jsou veřejně přístupné.','Die Gärten um das Lustschloss sind öffentlich zugänglich.'),('Budova je významnou kulturní památkou.','Das Gebäude ist ein bedeutendes Kulturdenkmal.'),('Architektura letohrádku je unikátní.','Die Architektur des Lustschlosses ist einzigartig.');
/*!40000 ALTER TABLE `kremlik_prag_25annalustschloss` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_26singendebrunnen`
--

DROP TABLE IF EXISTS `kremlik_prag_26singendebrunnen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_26singendebrunnen` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_26singendebrunnen`
--

LOCK TABLES `kremlik_prag_26singendebrunnen` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_26singendebrunnen` DISABLE KEYS */;
INSERT INTO `kremlik_prag_26singendebrunnen` VALUES ('fontána','der Brunnen'),('bronze','die Bronze'),('socha','die Statue'),('zvuk vody','das Wassergeräusch'),('ozvěna','das Echo'),('renesance','die Renaissance'),('hudba','die Musik'),('kovotepec','der Metallgießer'),('dekorace','die Dekoration'),('královský letohrádek','das Königliche Lustschloss'),('Zpívající fontána je renesanční klenot.','Der Singende Brunnen ist ein Renaissancejuwel.'),('Zvuk vody připomíná jemnou hudbu.','Das Geräusch des Wassers erinnert an sanfte Musik.'),('Fontána byla odlita z bronzu.','Der Brunnen wurde aus Bronze gegossen.'),('Nachází se v zahradách královny Anny.','Er befindet sich in den Gärten der Königin Anna.'),('Je to populární místo pro fotografy.','Es ist ein beliebter Ort für Fotografen.');
/*!40000 ALTER TABLE `kremlik_prag_26singendebrunnen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_27hradschinplatz`
--

DROP TABLE IF EXISTS `kremlik_prag_27hradschinplatz`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_27hradschinplatz` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_27hradschinplatz`
--

LOCK TABLES `kremlik_prag_27hradschinplatz` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_27hradschinplatz` DISABLE KEYS */;
INSERT INTO `kremlik_prag_27hradschinplatz` VALUES ('náměstí','der Platz'),('palác','der Palast'),('socha','die Statue'),('lampa','die Lampe'),('hradby','die Stadtmauer'),('chodník','der Gehweg'),('památka','das Denkmal'),('turisté','die Touristen'),('výhled','die Aussicht'),('architektura','die Architektur'),('Hradčanské náměstí je obklopeno významnými paláci.','Der Hradschin-Platz ist von bedeutenden Palästen umgeben.'),('Na náměstí stojí barokní sochy.','Auf dem Platz stehen barocke Statuen.'),('Turisté zde začínají prohlídku Pražského hradu.','Touristen beginnen hier die Besichtigung der Prager Burg.'),('Náměstí nabízí krásný výhled na Malou Stranu.','Der Platz bietet einen schönen Blick auf die Kleinseite.'),('Historická architektura je zde dobře zachovaná.','Die historische Architektur ist hier gut erhalten.');
/*!40000 ALTER TABLE `kremlik_prag_27hradschinplatz` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_28loreta`
--

DROP TABLE IF EXISTS `kremlik_prag_28loreta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_28loreta` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_28loreta`
--

LOCK TABLES `kremlik_prag_28loreta` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_28loreta` DISABLE KEYS */;
INSERT INTO `kremlik_prag_28loreta` VALUES ('Loreta','Loreto'),('poutní místo','der Wallfahrtsort'),('svatyně','das Heiligtum'),('zvonice','der Glockenturm'),('kaple','die Kapelle'),('socha','die Statue'),('nádvoří','der Innenhof'),('pokladnice','die Schatzkammer'),('perly','die Perlen'),('relikvie','die Reliquie'),('Loreta je slavné poutní místo v Praze.','Loreto ist ein berühmter Wallfahrtsort in Prag.'),('Součástí areálu je zvonice s proslulými zvony.','Zum Areal gehört ein Glockenturm mit berühmten Glocken.'),('Pokladnice obsahuje cenné relikvie a perly.','Die Schatzkammer enthält wertvolle Reliquien und Perlen.'),('Každou hodinu zvoní zvonkohra.','Jede Stunde läutet das Glockenspiel.'),('Loreta je obklopena barokní architekturou.','Loreto ist von barocker Architektur umgeben.');
/*!40000 ALTER TABLE `kremlik_prag_28loreta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_29strahovbibliotek`
--

DROP TABLE IF EXISTS `kremlik_prag_29strahovbibliotek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_29strahovbibliotek` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_29strahovbibliotek`
--

LOCK TABLES `kremlik_prag_29strahovbibliotek` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_29strahovbibliotek` DISABLE KEYS */;
INSERT INTO `kremlik_prag_29strahovbibliotek` VALUES ('knihovna','die Bibliothek'),('rukopis','das Manuskript'),('sál','der Saal'),('malba','das Gemälde'),('strop','die Decke'),('police','das Regal'),('kniha','das Buch'),('historie','die Geschichte'),('baroko','der Barock'),('archiv','das Archiv'),('Strahovská knihovna je známá svou nádhernou výzdobou.','Die Strahov-Bibliothek ist für ihre wunderschöne Ausstattung bekannt.'),('Obsahuje tisíce historických knih a rukopisů.','Sie enthält Tausende historischer Bücher und Manuskripte.'),('Stropy knihovny jsou zdobeny freskami.','Die Decken der Bibliothek sind mit Fresken verziert.'),('Návštěvníci obdivují cenné exponáty.','Besucher bewundern die wertvollen Exponate.'),('Je to poklad české kultury.','Es ist ein Schatz der tschechischen Kultur.');
/*!40000 ALTER TABLE `kremlik_prag_29strahovbibliotek` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_30wenzelskirche`
--

DROP TABLE IF EXISTS `kremlik_prag_30wenzelskirche`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_30wenzelskirche` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_30wenzelskirche`
--

LOCK TABLES `kremlik_prag_30wenzelskirche` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_30wenzelskirche` DISABLE KEYS */;
INSERT INTO `kremlik_prag_30wenzelskirche` VALUES ('kostel','die Kirche'),('svatý Václav','der heilige Wenzel'),('oltář','der Altar'),('věž','der Turm'),('varhany','die Orgel'),('socha svatého','die Statue des Heiligen'),('lavice','die Bank'),('vitráž','das Buntglasfenster'),('klenba','das Gewölbe'),('freska','das Fresko'),('Kostel sv. Václava je známý svou bohatou výzdobou.','Die Wenzelskirche ist für ihre reiche Ausstattung bekannt.'),('Na oltáři je socha sv. Václava.','Auf dem Altar steht die Statue des heiligen Wenzel.'),('Z věže je krásný výhled na město.','Vom Turm hat man einen schönen Blick auf die Stadt.'),('Interiér zdobí vitráže a fresky.','Das Innere ist mit Buntglasfenstern und Fresken geschmückt.'),('Varhany v kostele mají unikátní zvuk.','Die Orgel in der Kirche hat einen einzigartigen Klang.');
/*!40000 ALTER TABLE `kremlik_prag_30wenzelskirche` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_31jesulein`
--

DROP TABLE IF EXISTS `kremlik_prag_31jesulein`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_31jesulein` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_31jesulein`
--

LOCK TABLES `kremlik_prag_31jesulein` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_31jesulein` DISABLE KEYS */;
INSERT INTO `kremlik_prag_31jesulein` VALUES ('kostel','die Kirche'),('Panna Marie','die Jungfrau Maria'),('socha Jezulátka','die Statue des Prager Jesulein'),('oltář','der Altar'),('kaple','die Kapelle'),('mše','die Messe'),('svatba','die Hochzeit'),('varhany','die Orgel'),('freska','das Fresko'),('relikvie','die Reliquie'),('Kostel Panny Marie Vítězné je známý sochou Pražského Jezulátka.','Die Kirche Maria vom Siege ist für die Statue des Prager Jesulein bekannt.'),('Uvnitř kostela se konají pravidelné mše.','In der Kirche finden regelmäßig Messen statt.'),('Oltář je zdoben krásnými freskami.','Der Altar ist mit wunderschönen Fresken verziert.'),('Kostel je oblíbeným místem pro svatby.','Die Kirche ist ein beliebter Ort für Hochzeiten.'),('Relikvie v kostele mají velkou historickou hodnotu.','Die Reliquien in der Kirche haben einen großen historischen Wert.');
/*!40000 ALTER TABLE `kremlik_prag_31jesulein` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_32kleinseitnerring`
--

DROP TABLE IF EXISTS `kremlik_prag_32kleinseitnerring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_32kleinseitnerring` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_32kleinseitnerring`
--

LOCK TABLES `kremlik_prag_32kleinseitnerring` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_32kleinseitnerring` DISABLE KEYS */;
INSERT INTO `kremlik_prag_32kleinseitnerring` VALUES ('náměstí','der Platz'),('radnice','das Rathaus'),('kašna','der Brunnen'),('socha','die Statue'),('kostel','die Kirche'),('historická budova','das historische Gebäude'),('kavárna','das Café'),('restaurace','das Restaurant'),('tržiště','der Markt'),('turista','der Tourist'),('Malostranské náměstí je srdcem Malé Strany.','Der Kleinseitner Ring ist das Herz der Kleinseite.'),('Na náměstí se nachází barokní kašna.','Auf dem Platz befindet sich ein barocker Brunnen.'),('Okolní budovy mají bohatou historii.','Die umliegenden Gebäude haben eine reiche Geschichte.'),('Turisté zde často navštěvují kavárny a restaurace.','Touristen besuchen hier oft Cafés und Restaurants.'),('Na náměstí se pořádají různé kulturní akce.','Auf dem Platz finden verschiedene Kulturveranstaltungen statt.');
/*!40000 ALTER TABLE `kremlik_prag_32kleinseitnerring` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_33nikolauskirche`
--

DROP TABLE IF EXISTS `kremlik_prag_33nikolauskirche`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_33nikolauskirche` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_33nikolauskirche`
--

LOCK TABLES `kremlik_prag_33nikolauskirche` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_33nikolauskirche` DISABLE KEYS */;
INSERT INTO `kremlik_prag_33nikolauskirche` VALUES ('kostel','die Kirche'),('baroko','der Barock'),('kupole','die Kuppel'),('varhany','die Orgel'),('socha svatého','die Heiligenstatue'),('freska','das Fresko'),('kazatelna','die Kanzel'),('klenba','das Gewölbe'),('koncert','das Konzert'),('návštěva','der Besuch'),('Kostel sv. Mikuláše je vrcholné dílo barokní architektury.','Die Nikolauskirche ist ein Meisterwerk der barocken Architektur.'),('Kupole kostela je viditelná z mnoha míst v Praze.','Die Kuppel der Kirche ist von vielen Orten in Prag sichtbar.'),('Interiér je zdoben freskami a sochami.','Das Innere ist mit Fresken und Statuen geschmückt.'),('V kostele se pořádají varhanní koncerty.','In der Kirche finden Orgelkonzerte statt.'),('Je to oblíbená turistická památka.','Es ist ein beliebtes Touristenziel.');
/*!40000 ALTER TABLE `kremlik_prag_33nikolauskirche` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_34wallenstein`
--

DROP TABLE IF EXISTS `kremlik_prag_34wallenstein`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_34wallenstein` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_34wallenstein`
--

LOCK TABLES `kremlik_prag_34wallenstein` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_34wallenstein` DISABLE KEYS */;
INSERT INTO `kremlik_prag_34wallenstein` VALUES ('palác','der Palast'),('zahrada','der Garten'),('socha','die Statue'),('rybník','der Teich'),('fontána','der Brunnen'),('arkáda','die Arkade'),('historie','die Geschichte'),('umění','die Kunst'),('stavitel','der Baumeister'),('výstava','die Ausstellung'),('Valdštejnský palác je sídlem Senátu ČR.','Der Waldsteinpalast ist der Sitz des Senats der Tschechischen Republik.'),('Zahrada paláce je otevřena veřejnosti.','Der Garten des Palastes ist für die Öffentlichkeit zugänglich.'),('Uvnitř jsou vystaveny umělecké sbírky.','Im Inneren sind Kunstsammlungen ausgestellt.'),('Fontána a sochy v zahradě jsou velmi oblíbené.','Der Brunnen und die Statuen im Garten sind sehr beliebt.'),('Palác je významnou barokní památkou.','Der Palast ist ein bedeutendes barockes Denkmal.');
/*!40000 ALTER TABLE `kremlik_prag_34wallenstein` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_35kampa`
--

DROP TABLE IF EXISTS `kremlik_prag_35kampa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_35kampa` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_35kampa`
--

LOCK TABLES `kremlik_prag_35kampa` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_35kampa` DISABLE KEYS */;
INSERT INTO `kremlik_prag_35kampa` VALUES ('ostrov','die Insel'),('park','der Park'),('mlýn','die Mühle'),('socha','die Statue'),('lávka','die Brücke'),('řeka','der Fluss'),('tráva','das Gras'),('umělecké dílo','das Kunstwerk'),('památník','das Denkmal'),('výhled','die Aussicht'),('Kampa je oblíbený park na ostrově u Karlova mostu.','Kampa ist ein beliebter Park auf der Insel bei der Karlsbrücke.'),('V parku najdete známé sochy a umělecká díla.','Im Park finden Sie bekannte Statuen und Kunstwerke.'),('Z Kampy je krásný výhled na Vltavu.','Von der Kampa hat man einen schönen Blick auf die Moldau.'),('Mlýny na Kampě patří k historickým památkám.','Die Mühlen auf der Kampa gehören zu den historischen Denkmälern.'),('Park je ideální pro odpočinek.','Der Park ist ideal zum Entspannen.');
/*!40000 ALTER TABLE `kremlik_prag_35kampa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_36lennon`
--

DROP TABLE IF EXISTS `kremlik_prag_36lennon`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_36lennon` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_36lennon`
--

LOCK TABLES `kremlik_prag_36lennon` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_36lennon` DISABLE KEYS */;
INSERT INTO `kremlik_prag_36lennon` VALUES ('zeď','die Wand'),('graffiti','das Graffiti'),('umělec','der Künstler'),('mírové poselství','die Friedensbotschaft'),('barva','die Farbe'),('obraz','das Bild'),('kresba','die Zeichnung'),('hudebník','der Musiker'),('turista','der Tourist'),('symbol svobody','das Freiheitssymbol'),('John Lennonova zeď je symbolem svobody a míru.','Die John-Lennon-Mauer ist ein Symbol für Freiheit und Frieden.'),('Zeď je pokryta barevnými graffiti.','Die Wand ist mit bunten Graffiti bedeckt.'),('Turisté zde často pořizují fotografie.','Touristen machen hier oft Fotos.'),('Každý rok se zeď mění díky novým kresbám.','Jedes Jahr verändert sich die Wand durch neue Zeichnungen.'),('Je to populární turistická atrakce.','Es ist eine beliebte Touristenattraktion.');
/*!40000 ALTER TABLE `kremlik_prag_36lennon` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_37legionen`
--

DROP TABLE IF EXISTS `kremlik_prag_37legionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_37legionen` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_37legionen`
--

LOCK TABLES `kremlik_prag_37legionen` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_37legionen` DISABLE KEYS */;
INSERT INTO `kremlik_prag_37legionen` VALUES ('most','die Brücke'),('řeka','der Fluss'),('oblouk','der Bogen'),('lampy','die Lampen'),('socha','die Statue'),('tramvaj','die Straßenbahn'),('výhled','die Aussicht'),('chodník','der Gehweg'),('automobil','das Auto'),('historie','die Geschichte'),('Most Legií spojuje Národní divadlo s Malou Stranou.','Die Brücke der Legionen verbindet das Nationaltheater mit der Kleinseite.'),('Z mostu je nádherný výhled na Pražský hrad.','Von der Brücke hat man einen herrlichen Blick auf die Prager Burg.'),('Most byl postaven na začátku 20. století.','Die Brücke wurde Anfang des 20. Jahrhunderts erbaut.'),('Po mostě jezdí tramvaje i auta.','Über die Brücke fahren Straßenbahnen und Autos.'),('Je to důležitý dopravní uzel.','Es ist ein wichtiger Verkehrsknotenpunkt.');
/*!40000 ALTER TABLE `kremlik_prag_37legionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_38museum`
--

DROP TABLE IF EXISTS `kremlik_prag_38museum`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_38museum` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_38museum`
--

LOCK TABLES `kremlik_prag_38museum` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_38museum` DISABLE KEYS */;
INSERT INTO `kremlik_prag_38museum` VALUES ('muzeum','das Museum'),('výstava','die Ausstellung'),('sbírka','die Sammlung'),('přírodověda','die Naturwissenschaften'),('historie','die Geschichte'),('umění','die Kunst'),('architektura','die Architektur'),('vstupenka','das Ticket'),('turisté','die Touristen'),('socha','die Statue'),('Národní muzeum je největší muzeum v České republice.','Das Nationalmuseum ist das größte Museum der Tschechischen Republik.'),('Muzeum nabízí mnoho zajímavých výstav.','Das Museum bietet viele interessante Ausstellungen.'),('Budova je významnou architektonickou památkou.','Das Gebäude ist ein bedeutendes architektonisches Denkmal.'),('Turisté zde mohou vidět bohaté sbírky.','Touristen können hier reiche Sammlungen sehen.'),('Muzeum stojí na horním konci Václavského náměstí.','Das Museum steht am oberen Ende des Wenzelsplatzes.');
/*!40000 ALTER TABLE `kremlik_prag_38museum` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_39tanzendehaus`
--

DROP TABLE IF EXISTS `kremlik_prag_39tanzendehaus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_39tanzendehaus` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_39tanzendehaus`
--

LOCK TABLES `kremlik_prag_39tanzendehaus` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_39tanzendehaus` DISABLE KEYS */;
INSERT INTO `kremlik_prag_39tanzendehaus` VALUES ('budova','das Gebäude'),('architektura','die Architektur'),('moderní design','das moderne Design'),('sklo','das Glas'),('kancelář','das Büro'),('restaurace','das Restaurant'),('vyhlídka','die Aussichtsplattform'),('stavitel','der Baumeister'),('turisté','die Touristen'),('symbol města','das Stadtsymbol'),('Tančící dům je moderní architektonická ikona Prahy.','Das Tanzende Haus ist eine moderne architektonische Ikone Prags.'),('Budova má unikátní křivý tvar.','Das Gebäude hat eine einzigartige gebogene Form.'),('V horních patrech je restaurace s výhledem.','In den oberen Etagen befindet sich ein Restaurant mit Aussicht.'),('Je to populární místo pro fotografy.','Es ist ein beliebter Ort für Fotografen.'),('Tančící dům byl postaven v 90. letech.','Das Tanzende Haus wurde in den 90er Jahren gebaut.');
/*!40000 ALTER TABLE `kremlik_prag_39tanzendehaus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_40slavin`
--

DROP TABLE IF EXISTS `kremlik_prag_40slavin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_40slavin` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_40slavin`
--

LOCK TABLES `kremlik_prag_40slavin` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_40slavin` DISABLE KEYS */;
INSERT INTO `kremlik_prag_40slavin` VALUES ('hřbitov','der Friedhof'),('hrobka','die Gruft'),('socha','die Statue'),('náhrobek','der Grabstein'),('památka','das Denkmal'),('kaple','die Kapelle'),('pohřeb','die Beerdigung'),('historie','die Geschichte'),('umělec','der Künstler'),('památník','das Memorial'),('Vyšehradský hřbitov je místem odpočinku slavných osobností.','Der Vyšehrader Friedhof ist die Ruhestätte berühmter Persönlichkeiten.'),('Na hřbitově se nachází Slavín, společná hrobka významných Čechů.','Auf dem Friedhof befindet sich Slavín, die gemeinsame Gruft bedeutender Tschechen.'),('Hrobky jsou zdobeny sochami a památníky.','Die Gräber sind mit Statuen und Denkmälern geschmückt.'),('Je to klidné a historické místo.','Es ist ein ruhiger und historischer Ort.'),('Turisté sem chodí vzdát úctu významným osobám.','Touristen besuchen diesen Ort, um bedeutenden Persönlichkeiten Respekt zu zollen.');
/*!40000 ALTER TABLE `kremlik_prag_40slavin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_41karlsplatz`
--

DROP TABLE IF EXISTS `kremlik_prag_41karlsplatz`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_41karlsplatz` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_41karlsplatz`
--

LOCK TABLES `kremlik_prag_41karlsplatz` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_41karlsplatz` DISABLE KEYS */;
INSERT INTO `kremlik_prag_41karlsplatz` VALUES ('náměstí','der Platz'),('parčík','der Park'),('kašna','der Brunnen'),('kostel','die Kirche'),('historická budova','das historische Gebäude'),('radnice','das Rathaus'),('socha','die Statue'),('strom','der Baum'),('kultura','die Kultur'),('výstava','die Ausstellung'),('Karlovo náměstí je jedním z největších náměstí v Praze.','Der Karlsplatz ist einer der größten Plätze in Prag.'),('V jeho středu se nachází rozlehlý park.','In seiner Mitte befindet sich ein großer Park.'),('Na náměstí stojí několik historických budov.','Am Platz stehen mehrere historische Gebäude.'),('Každoročně se zde konají kulturní akce.','Jedes Jahr finden hier kulturelle Veranstaltungen statt.'),('Turisté obdivují sochy a kašny.','Touristen bewundern die Statuen und Brunnen.');
/*!40000 ALTER TABLE `kremlik_prag_41karlsplatz` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_42emauskloster`
--

DROP TABLE IF EXISTS `kremlik_prag_42emauskloster`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_42emauskloster` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_42emauskloster`
--

LOCK TABLES `kremlik_prag_42emauskloster` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_42emauskloster` DISABLE KEYS */;
INSERT INTO `kremlik_prag_42emauskloster` VALUES ('klášter','das Kloster'),('kostel','die Kirche'),('gotika','die Gotik'),('baroko','der Barock'),('freska','das Fresko'),('kaple','die Kapelle'),('nádvoří','der Hof'),('socha','die Statue'),('zahrada','der Garten'),('historie','die Geschichte'),('Emauzský klášter je významný historický komplex.','Das Emmauskloster ist ein bedeutender historischer Komplex.'),('Klášter byl založen ve 14. století.','Das Kloster wurde im 14. Jahrhundert gegründet.'),('Interiér je zdoben gotickými freskami.','Das Innere ist mit gotischen Fresken verziert.'),('Nádvoří kláštera je klidné a malebné.','Der Hof des Klosters ist ruhig und malerisch.'),('Klášter je otevřen pro návštěvníky a turisty.','Das Kloster ist für Besucher und Touristen geöffnet.');
/*!40000 ALTER TABLE `kremlik_prag_42emauskloster` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_43kasematen`
--

DROP TABLE IF EXISTS `kremlik_prag_43kasematen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_43kasematen` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_43kasematen`
--

LOCK TABLES `kremlik_prag_43kasematen` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_43kasematen` DISABLE KEYS */;
INSERT INTO `kremlik_prag_43kasematen` VALUES ('kasematy','die Kasematten'),('pevnost','die Festung'),('podzemí','das Untergrund'),('chodba','der Gang'),('socha','die Statue'),('památka','das Denkmal'),('průvodce','der Führer'),('vojenská historie','die Militärgeschichte'),('vstup','der Eingang'),('výstup','der Ausgang'),('Vyšehradské kasematy jsou součástí historické pevnosti.','Die Kasematten von Vyšehrad sind Teil der historischen Festung.'),('Podzemní chodby byly využívány pro vojenské účely.','Die unterirdischen Gänge wurden für militärische Zwecke genutzt.'),('Dnes jsou kasematy přístupné turistům.','Heute sind die Kasematten für Touristen zugänglich.'),('Uvnitř jsou umístěny sochy z Karlova mostu.','Im Inneren befinden sich Statuen von der Karlsbrücke.'),('Prohlídka s průvodcem trvá přibližně hodinu.','Die Führung mit einem Guide dauert etwa eine Stunde.');
/*!40000 ALTER TABLE `kremlik_prag_43kasematen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_prag_44fernsehturm`
--

DROP TABLE IF EXISTS `kremlik_prag_44fernsehturm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_prag_44fernsehturm` (
  `Czech` varchar(255) NOT NULL,
  `German` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_prag_44fernsehturm`
--

LOCK TABLES `kremlik_prag_44fernsehturm` WRITE;
/*!40000 ALTER TABLE `kremlik_prag_44fernsehturm` DISABLE KEYS */;
INSERT INTO `kremlik_prag_44fernsehturm` VALUES ('televizní věž','der Fernsehturm'),('vyhlídka','die Aussichtsplattform'),('elevator','der Aufzug'),('restaurace','das Restaurant'),('architektura','die Architektur'),('anténa','die Antenne'),('panoramatický výhled','die Panorama-Aussicht'),('umělecká instalace','die Kunstinstallation'),('dětský koutek','die Kinderecke'),('symbol města','das Stadtsymbol'),('Žižkovská televizní věž je nejvyšší stavbou v Praze.','Der Fernsehturm Žižkov ist das höchste Bauwerk in Prag.'),('Z vyhlídky je úžasný panoramatický výhled.','Von der Aussichtsplattform hat man eine großartige Panorama-Aussicht.'),('Věž obsahuje restauraci a kavárnu.','Der Turm beherbergt ein Restaurant und ein Café.'),('Na věži jsou známé sochy miminek od Davida Černého.','Am Turm befinden sich die bekannten Babyskulpturen von David Černý.'),('Je to moderní symbol Prahy.','Es ist ein modernes Symbol Prags.');
/*!40000 ALTER TABLE `kremlik_prag_44fernsehturm` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `languages_italian4`
--

DROP TABLE IF EXISTS `languages_italian4`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `languages_italian4` (
  `Czech` varchar(255) NOT NULL,
  `Italian` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages_italian4`
--

LOCK TABLES `languages_italian4` WRITE;
/*!40000 ALTER TABLE `languages_italian4` DISABLE KEYS */;
INSERT INTO `languages_italian4` VALUES ('Díky.','Grazie.'),('Dobrý den.','Buongiorno.'),('Nashledanou.','Arrivederci.'),('Jmenuji se.','Mi chiamo.'),('Prosím.','Per favore.');
/*!40000 ALTER TABLE `languages_italian4` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mastered_words`
--

DROP TABLE IF EXISTS `mastered_words`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mastered_words` (
  `source_word` varchar(255) DEFAULT NULL,
  `target_word` varchar(255) DEFAULT NULL,
  `language` varchar(50) DEFAULT NULL,
  `last_seen` timestamp NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mastered_words`
--

LOCK TABLES `mastered_words` WRITE;
/*!40000 ALTER TABLE `mastered_words` DISABLE KEYS */;
INSERT INTO `mastered_words` VALUES ('židle','chair','english','2025-07-19 10:16:40',20,24),('dřez','sink','english','2025-07-20 10:10:42',20,26),('dveře','door','english','2025-07-20 13:49:27',2,27),('sporák','cooker','english','2025-07-20 13:49:30',2,28),('konvice','kettle','english','2025-07-21 00:04:21',20,32),('Smetanova síň','der Smetana-Saal','german','2025-07-27 06:48:08',23,33),('národní cítění','das nationale Gefühl','german','2025-07-27 06:48:55',23,34),('Prašná brána je jednou z nejvýznamnějších gotických památek v Praze.','Der Pulverturm ist eines der bedeutendsten gotischen Bauwerke in Prag.','german','2025-07-27 07:08:01',23,35),('varhany','die Orgel','german','2025-07-27 07:36:27',23,36),('Po levé straně vidíte slavný Obecní dům.','Auf der linken Seite sehen Sie das berühmte Gemeindehaus.','german','2025-07-28 20:54:20',23,37),('interiér','das Interieur','german','2025-07-29 06:36:15',23,38),('nábytek','die Möbel','german','2025-07-29 06:36:21',23,39),('průvodce','der Reiseführer','german','2025-07-29 06:40:15',23,40),('Interiér obsahuje původní kubistický nábytek a dekorace.','Das Interieur enthält originale kubistische Möbel und Dekorationen.','german','2025-07-29 06:45:15',23,41),('dar','das Geschenk','german','2025-08-01 14:37:02',23,42),('střelný prach','das Schießpulver','german','2025-08-01 14:37:09',23,43),('významná památka','das bedeutende Denkmal','german','2025-08-01 14:37:22',23,44),('vstup do Starého Města','der Eingang zur Altstadt','german','2025-08-01 14:37:26',23,45),('Prašná brána','der Pulverturm','german','2025-08-03 12:59:41',23,46),('Nachází se poblíž Ungeltu a Staroměstského náměstí.','Sie befindet sich in der Nähe des Ungelt und des Altstädter Rings.','german','2025-08-03 12:59:43',23,47),('Bazilika je oblíbeným místem pro koncerty vážné hudby.','Die Basilika ist ein beliebter Ort für klassische Musikkonzerte.','german','2025-08-03 12:59:55',23,48),('Název ‚Prašná brána‘ získala ve 17. století, kdy zde bylo skladováno střelné prach.','Den Namen ‚Pulverturm‘ erhielt sie im 17. Jahrhundert, als hier Schießpulver gelagert wurde.','german','2025-08-03 13:00:43',23,49),('Stropní fresky a sochy vytvářejí působivý interiér.','Die Deckenfresken und Statuen schaffen ein beeindruckendes Interieur.','german','2025-08-03 13:05:31',23,50),('vybavení','die Ausrüstung','german','2025-08-03 13:07:21',23,51),('výtah','der Aufzug','german','2025-08-03 13:07:32',23,52),('Byla postavena na gotických základech a později přestavěna v barokním stylu.','Sie wurde auf gotischen Fundamenten errichtet und später im Barockstil umgebaut.','german','2025-08-03 16:52:37',23,53),('socha sv. Jakuba','die Statue des heiligen Jakobus','german','2025-08-03 16:52:40',23,54),('Dnes je v domě muzeum českého kubismu.','Heute befindet sich in dem Haus das Museum des tschechischen Kubismus.','german','2025-08-03 16:52:42',23,55),('Dům U Černé Matky Boží je slavná kubistická budova v Praze.','Das Haus Zur Schwarzen Mutter Gottes ist ein berühmtes kubistisches Gebäude in Prag.','german','2025-08-03 16:52:45',23,56),('socha černé madony','die Statue der schwarzen Madonna','german','2025-08-03 16:52:47',23,57),('Brána je zdobena sochami českých králů, například Karla IV. a Jiřího z Poděbrad.','Das Tor ist mit Statuen böhmischer Könige verziert, wie Karl IV. und Georg von Poděbrad.','german','2025-08-03 16:52:50',23,58),('gotický styl','der gotische Stil','german','2025-08-03 16:52:52',23,59),('Fasáda je ozdobena mozaikami a sochami českých umělců.','Die Fassade ist mit Mosaiken und Statuen tschechischer Künstler geschmückt.','german','2025-08-03 16:52:55',23,60),('Bazilika sv. Jakuba je známá svou bohatou barokní výzdobou.','Die Jakobskirche ist für ihre reiche barocke Ausstattung bekannt.','german','2025-08-03 16:52:57',23,61),('umělec','der Künstler','german','2025-08-03 16:54:39',23,62),('restaurace','die Restaurierung','german','2025-08-03 16:54:42',23,63),('gotický základ','das gotische Fundament','german','2025-08-03 16:54:44',23,64),('kazatelna','die Kanzel','german','2025-08-03 16:54:47',23,65),('stropní freska','das Deckenfresko','german','2025-08-03 16:54:50',23,66),('hrobka','die Gruft','german','2025-08-03 16:54:52',23,67),('klášter','das Kloster','german','2025-08-03 16:54:55',23,68),('Budova byla postavena v letech 1911–1912 podle návrhu Josefa Gočára.','Das Gebäude wurde in den Jahren 1911–1912 nach den Plänen von Josef Gočár errichtet.','german','2025-08-03 16:54:58',23,69),('geometrické tvary','die geometrischen Formen','german','2025-08-03 16:55:00',23,70),('klimatizace','die Klimaanlage','german','2025-08-03 16:55:05',23,71),('výjimečný příklad','das herausragende Beispiel','german','2025-08-03 16:55:08',23,72),('baroko','das Barock','german','2025-08-03 16:55:10',23,73),('ocelobetonová konstrukce','die Stahlbetonkonstruktion','german','2025-08-03 16:55:12',23,74),('kubismus','der Kubismus','german','2025-08-03 16:55:14',23,75),('Dům U Černé Matky Boží','Haus Zur Schwarzen Mutter Gottes','german','2025-08-03 16:55:16',23,76),('Je to oblíbený cíl turistů a vstupní brána do Starého Města.','Sie ist ein beliebtes Ziel für Touristen und der Eingang zur Altstadt.','german','2025-08-03 16:55:17',23,77),('Byla postavena v roce 1475 jako náhrada za původní městskou bránu.','Er wurde im Jahr 1475 als Ersatz für ein ursprüngliches Stadttor erbaut.','german','2025-08-03 16:55:19',23,78),('Autorem stavby je Matěj Rejsek z Prostějova.','Der Bau stammt von Matěj Rejsek aus Prostějov.','german','2025-08-03 16:55:22',23,79),('Dnes se zde konají koncerty a významné akce.','Heute finden hier Konzerte und wichtige Veranstaltungen statt.','german','2025-08-03 16:55:26',23,80),('městská brána','das Stadttor','german','2025-08-03 16:55:29',23,81),('Zde byla 28. října 1918 vyhlášena nezávislá Československá republika.','Hier wurde am 28. Oktober 1918 die unabhängige Tschechoslowakische Republik ausgerufen.','german','2025-08-03 16:55:32',23,82),('Je to jeden z nejkrásnějších příkladů secese v Praze.','Es ist eines der schönsten Beispiele des Jugendstils in Prag.','german','2025-08-03 16:55:36',23,83),('secese (art nouveau)','der Jugendstil','german','2025-08-03 16:55:39',23,84),('mozaika','das Mosaik','german','2025-08-03 16:55:40',23,85),('Jmenuji se.','Mi chiamo.','italian','2025-08-11 18:04:52',23,86),('Dobrý den.','Buen día.','spanish','2025-08-11 20:15:00',23,87),('Jak se máte?','¿Cómo estás?','spanish','2025-08-11 20:15:02',23,88),('Miluji vás.','Te amo.','spanish','2025-08-11 20:15:04',23,89),('Prosím.','Por favor.','spanish','2025-08-11 20:15:06',23,90),('Nashledanou.','Adiós.','spanish','2025-08-11 20:15:09',23,91),('Díky.','Gracias.','spanish','2025-08-11 20:15:14',23,92);
/*!40000 ALTER TABLE `mastered_words` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_choices_kremlik_languages_french3`
--

DROP TABLE IF EXISTS `quiz_choices_kremlik_languages_french3`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_choices_kremlik_languages_french3` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `wrong1` text DEFAULT NULL,
  `wrong2` text DEFAULT NULL,
  `wrong3` text DEFAULT NULL,
  `source_lang` varchar(50) DEFAULT NULL,
  `target_lang` varchar(50) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `ai_candidates` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_choices_kremlik_languages_french3`
--

LOCK TABLES `quiz_choices_kremlik_languages_french3` WRITE;
/*!40000 ALTER TABLE `quiz_choices_kremlik_languages_french3` DISABLE KEYS */;
INSERT INTO `quiz_choices_kremlik_languages_french3` VALUES (1,'Díky.','Merci.','Merci beaucoup','Pardon','Excusez-moi','Czech','French',NULL,'Merci beaucoup | Pardon | Excusez-moi | Je vous en prie | Bienvenue | Désolé | S\'il te plaît | Je t\'aime | À bientôt | Pas de problème'),(2,'Dobrý den.','Bonjour.','Bonsoir','Salut','Bonne nuit','Czech','French',NULL,'Bonsoir | Salut | Bonne nuit | Bonne journée | Enchanté | Comment ça va | À demain | Coucou | Bienvenu | Pardon'),(3,'Nashledanou.','Au revoir.','Adieu','Salut','Ciao','Czech','French',NULL,'Adieu | À plus tard | À demain | Salut | Ciao | Bonne nuit | Bonne journée | À bientôt | Je m\'en vais | Prends soin'),(4,'Jmenuji se.','Mon nom est.','Je suis','Je me présente','Voici mon nom','Czech','French',NULL,'Je suis | Je m\'appelle | Je me présente | Voici mon nom | C\'est moi | Moi c\'est | Je suis appelé | Mon prénom est | Je me nomme | Je suis nommé'),(5,'Prosím.','S\'il te plaît.','Je te prie','Je vous en prie','Merci','Czech','French',NULL,'S\'il vous plaît | Je te prie | Je vous en prie | Merci | De rien | Excuse-moi | Pardon | Je t\'en supplie | Fais-moi plaisir | À votre service');
/*!40000 ALTER TABLE `quiz_choices_kremlik_languages_french3` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_choices_kremlik_languages_italian5`
--

DROP TABLE IF EXISTS `quiz_choices_kremlik_languages_italian5`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_choices_kremlik_languages_italian5` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `wrong1` text DEFAULT NULL,
  `wrong2` text DEFAULT NULL,
  `wrong3` text DEFAULT NULL,
  `source_lang` varchar(50) DEFAULT NULL,
  `target_lang` varchar(50) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `ai_candidates` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_choices_kremlik_languages_italian5`
--

LOCK TABLES `quiz_choices_kremlik_languages_italian5` WRITE;
/*!40000 ALTER TABLE `quiz_choices_kremlik_languages_italian5` DISABLE KEYS */;
INSERT INTO `quiz_choices_kremlik_languages_italian5` VALUES (6,'Díky.','Grazie.','Grazia','Grazioso','Grato','Czech','Italian','quiz_logo.png','Grazia | Grazioso | Grato | Gratta | Graziano | Grazia mia | Grazie mille | Grazie tante | Grazie a Dio | Grazie a te | Grazie per | Grazie di cuore'),(7,'Dobrý den.','Buongiorno.','Buonasera','Buonanotte','Buon pomeriggio','Czech','Italian','quiz_logo.png','Buonasera | Buonanotte | Buon pomeriggio | Buon giorno | Buon appetito | Buon compleanno | Buon viaggio | Buon divertimento | Buon lavoro | Buon weekend | Buon Natale | Buon anno'),(8,'Nashledanou.','Arrivederci.','Arrivederla','Arrivederci presto','Arrivederci a presto','Czech','Italian','quiz_logo.png','Arrivederla | Arrivederci presto | Arrivederci a presto | Arrivederci domani | Arrivederci più tardi | Arrivederci alla prossima | Arrivederci amico | Arrivederci ragazzi | Arrivederci e grazie | Arrivederci per ora | Arrivederci a dopo | Arrivederci a domani'),(9,'Jmenuji se.','Mi chiamo.','Mi chiamano','Mi chiami','Mi chiama','Czech','Italian','quiz_logo.png','Mi chiamano | Mi chiami | Mi chiama | Mi chiamo io | Mi chiamo così | Mi chiamo come | Mi chiamo anche | Mi chiamo solo | Mi chiamo sempre | Mi chiamo mai | Mi chiamo ancora | Mi chiamo proprio'),(10,'Prosím.','Per favore.','Per piacere','Per cortesia','Per favore mio','Czech','Italian','quiz_logo.png','Per piacere | Per cortesia | Per favore mio | Per favore tuo | Per favore nostro | Per favore vostro | Per favore loro | Per favore stesso | Per favore ancora | Per favore subito | Per favore ora | Per favore dopo');
/*!40000 ALTER TABLE `quiz_choices_kremlik_languages_italian5` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_choices_kremlik_languages_spanish`
--

DROP TABLE IF EXISTS `quiz_choices_kremlik_languages_spanish`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_choices_kremlik_languages_spanish` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `wrong1` text DEFAULT NULL,
  `wrong2` text DEFAULT NULL,
  `wrong3` text DEFAULT NULL,
  `source_lang` varchar(50) DEFAULT NULL,
  `target_lang` varchar(50) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `ai_candidates` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_choices_kremlik_languages_spanish`
--

LOCK TABLES `quiz_choices_kremlik_languages_spanish` WRITE;
/*!40000 ALTER TABLE `quiz_choices_kremlik_languages_spanish` DISABLE KEYS */;
/*!40000 ALTER TABLE `quiz_choices_kremlik_languages_spanish` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_choices_kremlik_prag_02pulverturm`
--

DROP TABLE IF EXISTS `quiz_choices_kremlik_prag_02pulverturm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_choices_kremlik_prag_02pulverturm` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `wrong1` text DEFAULT NULL,
  `wrong2` text DEFAULT NULL,
  `wrong3` text DEFAULT NULL,
  `source_lang` varchar(50) DEFAULT NULL,
  `target_lang` varchar(50) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `ai_candidates` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_choices_kremlik_prag_02pulverturm`
--

LOCK TABLES `quiz_choices_kremlik_prag_02pulverturm` WRITE;
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_02pulverturm` DISABLE KEYS */;
INSERT INTO `quiz_choices_kremlik_prag_02pulverturm` VALUES (1,'Prašná brána','der Pulverturm','der Pulverberg','die Pulverhalle','das Pulverhaus','Czech','German','uploads/quiz_images/quiz_1_689a413e0eb8d.jpg','der Pulverberg | die Pulverhalle | das Pulverhaus | der Schießturm | die Staubtür | der Wachturm | das Munitionslager | die Explosivkammer | der Kanonenturm | die Schießkammer'),(2,'gotický styl','der gotische Stil','der Gothikstil','die Gotik','der mittelalterliche Stil','Czech','German','uploads/quiz_images/quiz_2_689a413f71d81.jpg','der Gothikstil | die Gotik | der mittelalterliche Stil | das Gotikdesign | die Gothic-Art | der romanische Stil | der Renaissancestil | die Mittelalterkunst | der Kirchenstil | die Architekturmode'),(3,'městská brána','das Stadttor','die Stadtmauer','der Stadteingang','das Stadttürchen','Czech','German','uploads/quiz_images/quiz_3_689a4140137f0.jpg','die Stadtmauer | der Stadteingang | das Stadttürchen | die Stadttür | der Stadteingangsturm | das Stadtschloss | die Stadtfestung | der Mauerdurchgang | das Burgtor | die Eingangspforte'),(5,'dar','das Geschenk','die Gabe','das Präsent','die Spende','Czech','German','uploads/quiz_images/quiz_5_689a414091c0a.jpg','die Gabe | das Präsent | die Spende | die Gabung | das Mitbringsel | die Schenkung | das Gabengeschenk | die Überraschung | das Dankeschön | die Zuwendung'),(6,'socha','die Statue','die Skulptur','das Standbild','die Figur','Czech','German','uploads/quiz_images/quiz_6_689a414124bb7.jpg','die Skulptur | das Standbild | die Figur | das Denkmal | die Plastik | das Kunstwerk | die Büste | das Relief | die Abbildung | die Darstellung'),(7,'střelný prach','das Schießpulver','das Schwarzpulver','die Schießwolle','das Treibmittel','Czech','German','uploads/quiz_images/quiz_7_689a4141a5254.png','das Schwarzpulver | die Schießwolle | das Treibmittel | die Sprengstoff | das Knallpulver | die Munition | das Explosivpulver | die Pulvermunition | das Feuerpulver | die Treibladung'),(8,'historické centrum','das historische Zentrum','die historische Mitte','das Altstadtzentrum','die Altstadt','Czech','German','uploads/quiz_images/quiz_8_689a41424431b.jpg','die historische Mitte | das Altstadtzentrum | die Altstadt | das Stadtzentrum | die Innenstadt | das Kulturzentrum | die Stadthistorie | das Denkmalzentrum | die Geschichtsstadt | das Traditionsviertel'),(10,'významná památka','das bedeutende Denkmal','das wichtige Monument','die bedeutsame Sehenswürdigkeit','das historische Monument','Czech','German','uploads/quiz_images/quiz_10_689a4142c0e9e.jpg','das wichtige Monument | die bedeutsame Sehenswürdigkeit | das historische Monument | die berühmte Stätte | das kulturelle Erbe | die wichtige Stätte | das berühmte Bauwerk | die historische Stätte | das kulturelle Monument | die berühmte Attraktion');
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_02pulverturm` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_choices_kremlik_prag_04jacob`
--

DROP TABLE IF EXISTS `quiz_choices_kremlik_prag_04jacob`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_choices_kremlik_prag_04jacob` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `wrong1` text DEFAULT NULL,
  `wrong2` text DEFAULT NULL,
  `wrong3` text DEFAULT NULL,
  `source_lang` varchar(50) DEFAULT NULL,
  `target_lang` varchar(50) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `ai_candidates` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_choices_kremlik_prag_04jacob`
--

LOCK TABLES `quiz_choices_kremlik_prag_04jacob` WRITE;
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_04jacob` DISABLE KEYS */;
INSERT INTO `quiz_choices_kremlik_prag_04jacob` VALUES (1,'Bazilika svatého Jakuba','Jakobskirche','Jakobskathedrale','Jakobsdom','Jakobskapelle','Czech','German',NULL,'Jakobskathedrale | Jakobsdom | Jakobskapelle | Sankt-Jakobs-Kirche | Jakobstempel | Jakobshalle | Jakobsbasilika | Jakobshaus | Jakobsgebäude | Jakobsheiligtum'),(2,'baroko','der Barock','die Barocke','der Barockstil','das Barocke','Czech','German',NULL,'die Barocke | der Barockstil | das Barocke | die Barockzeit | der Barockismus | das Barocktum | die Barockik | der Barockismus | das Barockische | die Barockperiode'),(3,'kostel','die Kirche','der Kirchenbau','die Kapelle','das Gotteshaus','Czech','German',NULL,'der Kirchenbau | die Kapelle | das Gotteshaus | die Kathedrale | der Dom | die Basilika | die Abtei | die Münster | die Pfarrkirche | die Synagoge'),(4,'klášter','das Kloster','die Abtei','das Stift','die Priorei','Czech','German',NULL,'die Abtei | das Stift | die Priorei | das Konvent | die Mönchsgemeinschaft | die Klause | das Nonnenkloster | die Zelle | die Eremitage | die Mönchsabtei');
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_04jacob` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_choices_kremlik_prag_05ungelt`
--

DROP TABLE IF EXISTS `quiz_choices_kremlik_prag_05ungelt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_choices_kremlik_prag_05ungelt` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `wrong1` text DEFAULT NULL,
  `wrong2` text DEFAULT NULL,
  `wrong3` text DEFAULT NULL,
  `source_lang` varchar(50) DEFAULT NULL,
  `target_lang` varchar(50) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `ai_candidates` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_choices_kremlik_prag_05ungelt`
--

LOCK TABLES `quiz_choices_kremlik_prag_05ungelt` WRITE;
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_05ungelt` DISABLE KEYS */;
INSERT INTO `quiz_choices_kremlik_prag_05ungelt` VALUES (1,'Ungelt','Ungelt','der Ungeld','die Ungelt','das Ungelt','Czech','German',NULL,'der Ungeld | die Ungelt | das Ungelt | der Zoll | die Steuer | das Geld | der Handel | die Abgabe | das Gebühr | der Preis'),(2,'celní dvůr','der Zollhof','die Zoll','das Zollhof','der Hofzoll','Czech','German',NULL,'die Zoll | das Zollhof | der Hofzoll | die Zollstelle | der Zollamt | das Zollhaus | die Zollgebühr | der Zolltarif | das Zollgebäude | die Zollkontrolle'),(3,'středověk','das Mittelalter','die Mittelalter','der Mittelalter','das Mittelalt','Czech','German',NULL,'die Mittelalter | der Mittelalter | das Mittelalt | die Mittelzeit | der Altezeit | das Altermittel | die Vergangenheit | der Geschichtszeit | das Historium | die Vorzeit'),(4,'obchodníci','die Händler','der Händel','die Handel','das Händler','Czech','German',NULL,'der Händel | die Handel | das Händler | der Verkäufer | die Kaufleute | das Geschäft | der Markt | die Verkäuferin | das Kaufhaus | der Kunde'),(5,'městská brána','das Stadttor','die Stadttor','der Torstadt','das Stadtportal','Czech','German',NULL,'die Stadttor | der Torstadt | das Stadtportal | die Stadtmauer | der Eingang | das Torhaus | die Pforte | der Durchgang | das Stadttür | die Tür'),(6,'dům','das Haus','die Haus','der Haus','das Hause','Czech','German',NULL,'die Haus | der Haus | das Hause | die Wohnung | der Wohnhaus | das Gebäude | die Hütte | der Bau | das Heim | die Unterkunft'),(7,'sklad','das Lager','die Lager','der Lager','das Lagerhaus','Czech','German',NULL,'die Lager | der Lager | das Lagerhaus | die Vorrat | der Speicher | das Depot | die Ablage | der Schuppen | das Magazin | die Kammer'),(8,'historické centrum','das historische Zentrum','die historische Zentrum','der historische Zentrum','das historisch Zentrum','Czech','German',NULL,'die historische Zentrum | der historische Zentrum | das historisch Zentrum | die Altstadt | der Stadtkern | das Stadtzentrum | die Innenstadt | der Mittelpunkt | das Herz | die Mitte'),(9,'arcibiskupský palác','der Erzbischöfliche Palast','die Erzbischöfliche Palast','der Erzbischofpalast','das Erzbischöfliche Palais','Czech','German',NULL,'die Erzbischöfliche Palast | der Erzbischofpalast | das Erzbischöfliche Palais | die Bischofspalast | der Kardinalspalast | das Päpstliche Palast | die Kathedrale | der Dom | das Kloster | die Abtei'),(10,'restaurace','das Restaurant','die Restaurant','der Restaurant','das Restaurante','Czech','German',NULL,'die Restaurant | der Restaurant | das Restaurante | die Gaststätte | der Gasthof | das Lokal | die Kneipe | der Imbiss | das Bistro | die Bar'),(11,'kavárna','das Café','die Café','der Café','das Kaffee','Czech','German',NULL,'die Café | der Café | das Kaffee | die Kaffeestube | der Kaffeehaus | das Bistro | die Teestube | der Espresso | das Kaffeehaus | die Konditorei'),(12,'nádvoří','der Innenhof','die Innenhof','das Innenhof','der Hofinnen','Czech','German',NULL,'die Innenhof | das Innenhof | der Hofinnen | die Außenhof | der Garten | das Atrium | die Terrasse | der Platz | das Freigelände | die Grünfläche'),(13,'románská kaple','die romanische Kapelle','der romanische Kapelle','die Romanenkapelle','das romanisch Kapelle','Czech','German',NULL,'der romanische Kapelle | die Romanenkapelle | das romanisch Kapelle | die Gotische Kapelle | der Kapellenroman | das Kapellchen | die Kirche | der Dom | das Münster | die Kathedrale'),(14,'památník','das Denkmal','die Denkmal','der Denkmal','das Denkmahl','Czech','German',NULL,'die Denkmal | der Denkmal | das Denkmahl | die Erinnerung | der Gedenkstein | das Monument | die Statue | der Obelisk | das Mahnmal | die Plakette'),(15,'turistické místo','der Touristenort','die Touristenort','der Touristenplatz','das Touristenort','Czech','German',NULL,'die Touristenort | der Touristenplatz | das Touristenort | die Sehenswürdigkeit | der Urlaubsort | das Reiseziel | die Attraktion | der Ferienort | das Ausflugsziel | die Destination');
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_05ungelt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_choices_kremlik_prag_06altstadterring`
--

DROP TABLE IF EXISTS `quiz_choices_kremlik_prag_06altstadterring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_choices_kremlik_prag_06altstadterring` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `wrong1` text DEFAULT NULL,
  `wrong2` text DEFAULT NULL,
  `wrong3` text DEFAULT NULL,
  `source_lang` varchar(50) DEFAULT NULL,
  `target_lang` varchar(50) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `ai_candidates` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_choices_kremlik_prag_06altstadterring`
--

LOCK TABLES `quiz_choices_kremlik_prag_06altstadterring` WRITE;
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_06altstadterring` DISABLE KEYS */;
INSERT INTO `quiz_choices_kremlik_prag_06altstadterring` VALUES (1,'Staroměstské náměstí','Altstädter Ring','Altstädter Ringx','gniR retd??tstlA','wrong','Czech','German',NULL,''),(2,'radnice','das Rathaus','das Rathausx','wrong','','Czech','German',NULL,''),(3,'orloj','die astronomische Uhr','die astronomische Uhrx','wrong','','Czech','German',NULL,''),(4,'Jan Hus','Jan Hus','Jan Husx','wrong','','Czech','German',NULL,''),(5,'pomník','das Denkmal','das Denkmalx','wrong','','Czech','German',NULL,''),(6,'kostel Panny Marie před Týnem','Kirche Maria vor dem Tein','Kirche Maria vor dem Teinx','wrong','','Czech','German',NULL,''),(7,'gotika','die Gotik','die Gotikx','wrong','','Czech','German',NULL,''),(8,'baroko','der Barock','der Barockx','wrong','','Czech','German',NULL,''),(9,'tržiště','der Marktplatz','der Marktplatzx','wrong','','Czech','German',NULL,''),(10,'měšťanský dům','das Bürgerhaus','das Bürgerhausx','suahregr??B sad','wrong','Czech','German',NULL,''),(11,'náměstí','der Platz','der Platzx','wrong','','Czech','German',NULL,''),(12,'turistický cíl','das Touristenziel','das Touristenzielx','wrong','','Czech','German',NULL,''),(13,'památná událost','das historische Ereignis','das historische Ereignisx','wrong','','Czech','German',NULL,''),(14,'slavnost','die Feier','die Feierx','wrong','','Czech','German',NULL,'');
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_06altstadterring` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_choices_kremlik_prag_28loreta`
--

DROP TABLE IF EXISTS `quiz_choices_kremlik_prag_28loreta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_choices_kremlik_prag_28loreta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `wrong1` text DEFAULT NULL,
  `wrong2` text DEFAULT NULL,
  `wrong3` text DEFAULT NULL,
  `source_lang` varchar(50) DEFAULT NULL,
  `target_lang` varchar(50) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `ai_candidates` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_choices_kremlik_prag_28loreta`
--

LOCK TABLES `quiz_choices_kremlik_prag_28loreta` WRITE;
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_28loreta` DISABLE KEYS */;
INSERT INTO `quiz_choices_kremlik_prag_28loreta` VALUES (1,'Loreta','Loreto','Loretox','wrong','','Czech','German',NULL,''),(2,'poutní místo','der Wallfahrtsort','der Wallfahrtsortx','wrong','','Czech','German',NULL,''),(3,'svatyně','das Heiligtum','das Heiligtumx','wrong','','Czech','German',NULL,''),(4,'zvonice','der Glockenturm','der Glockenturmx','wrong','','Czech','German',NULL,''),(5,'kaple','die Kapelle','die Kapellex','wrong','','Czech','German',NULL,''),(6,'socha','die Statue','die Statuex','wrong','','Czech','German',NULL,''),(7,'nádvoří','der Innenhof','der Innenhofx','wrong','','Czech','German',NULL,''),(8,'pokladnice','die Schatzkammer','die Schatzkammerx','wrong','','Czech','German',NULL,''),(9,'perly','die Perlen','die Perlenx','wrong','','Czech','German',NULL,''),(10,'relikvie','die Reliquie','die Reliquiex','wrong','','Czech','German',NULL,'');
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_28loreta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_choices_kremlik_prag_34wallenstein`
--

DROP TABLE IF EXISTS `quiz_choices_kremlik_prag_34wallenstein`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_choices_kremlik_prag_34wallenstein` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `wrong1` text DEFAULT NULL,
  `wrong2` text DEFAULT NULL,
  `wrong3` text DEFAULT NULL,
  `source_lang` varchar(50) DEFAULT NULL,
  `target_lang` varchar(50) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `ai_candidates` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_choices_kremlik_prag_34wallenstein`
--

LOCK TABLES `quiz_choices_kremlik_prag_34wallenstein` WRITE;
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_34wallenstein` DISABLE KEYS */;
INSERT INTO `quiz_choices_kremlik_prag_34wallenstein` VALUES (1,'palác','der Palast','der Platz','die Palme','der Pfahl','Czech','German','uploads/quiz_images/quiz_1_6899f67770562.jpg','der Platz | die Palme | der Pfahl | das Palais | der Pakt | die Palette | der Pfalz | die Pforte | der Paladin | der Panzer | die Pause | der Pass'),(2,'zahrada','der Garten','die Zähre','der Zaun','die Zange','Czech','German','uploads/quiz_images/quiz_2_6899f67801d63.jpg','die Zähre | der Zaun | die Zange | der Zahn | die Zeder | der Zirkus | die Zelle | der Zaster | die Zucht | der Zopf | die Zier | der Zoll'),(3,'socha','die Statue','die Statur','die Stätte','die Stange','Czech','German','quiz_logo.png','die Statur | die Stätte | die Stange | die Stute | die Stola | die Stufe | die Stulle | die Stute | die Stelze | die Stoppel | die Stulle | die Stange'),(4,'rybník','der Teich','der Rabe','die Rübe','der Rand','Czech','German','quiz_logo.png','der Rabe | die Rübe | der Rand | der Riegel | der Ritt | die Rinde | der Riss | der Rücken | die Rüstung | der Ruf | die Rute | der Rumpf'),(5,'fontána','der Brunnen','die Fonte','die Front','die Furt','Czech','German','quiz_logo.png','die Fonte | die Front | die Furt | die Furche | die Fackel | die Falte | die Ferse | die Fichte | die Flasche | die Flöte | die Flut | die Folie'),(6,'arkáda','die Arkade','die Arie','die Arena','die Armee','Czech','German','quiz_logo.png','die Arie | die Arena | die Armee | die Arke | die Arznei | die Art | die Asche | die Axt | die Achse | die Ader | die Ampel | die Angel'),(7,'historie','die Geschichte','die Historien','das Geschichte','Das Passierte','Czech','German','quiz_logo.png','die Historie | die Historie | die Historie | die Historie | die Historie | die Historie | die Historie | die Historie | die Historie | die Historie | die Historie | die Historie'),(8,'umění','die Kunst','der Umhang','die Umkehr','die Umrandung','Czech','German','quiz_logo.png','der Umhang | die Umkehr | die Umrandung | die Umarmung | die Umfrage | die Umleitung | die Umschau | die Umstellung | die Umwandlung | die Umwälzung | die Umzäunung | die Umkehr'),(9,'stavitel','der Baumeister','der Stamm','der Stapel','der Ständer','Czech','German','quiz_logo.png','der Stamm | der Stapel | der Ständer | der Stahl | der Stamm | der Stapel | der Ständer | der Stahl | der Stamm | der Stapel | der Ständer | der Stahl'),(10,'výstava','die Ausstellung','die Auslage','die Ausfahrt','die Ausrede','Czech','German','quiz_logo.png','die Auslage | die Ausfahrt | die Ausrede | die Auszeit | die Auswahl | die Auskunft | die Ausnahme | die Ausrüstung | die Aussicht | die Ausflucht | die Auszeichnung | die Ausbeute');
/*!40000 ALTER TABLE `quiz_choices_kremlik_prag_34wallenstein` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shared_tables`
--

DROP TABLE IF EXISTS `shared_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shared_tables` (
  `table_name` varchar(255) NOT NULL,
  `owner` varchar(64) NOT NULL,
  `shared_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shared_tables`
--

LOCK TABLES `shared_tables` WRITE;
/*!40000 ALTER TABLE `shared_tables` DISABLE KEYS */;
/*!40000 ALTER TABLE `shared_tables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (26,'Victor','$2y$10$GsJTtJzDGI4Ubw/6JHoe2.6k1TOGFW9o88B1tTbgZI2sSyhdyCIfK','2025-08-06 08:18:24','kremlik@seznam.cz'),(23,'Kremlik','$2y$10$TwRUxWpBE7EYA.wkjJSx9eI0yQxe7PaYeDCvYVq7LMP1DUC8eef.y','2025-07-26 20:32:02',NULL),(25,'Admin','$2y$10$VULkbTkamGYUXoWcPB36E.qEDYmOul7qrpISRiNgaYLbmFOFdc0jS','2025-08-06 08:05:50','kremlik@seznam.cz');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wb_files`
--

DROP TABLE IF EXISTS `wb_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wb_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` varchar(191) NOT NULL,
  `path` varchar(1024) NOT NULL,
  `label` varchar(255) NOT NULL,
  `kind` enum('table','file') NOT NULL DEFAULT 'table',
  `is_dir` tinyint(1) NOT NULL DEFAULT 0,
  `is_shared` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_owner_path` (`owner`,`path`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wb_files`
--

LOCK TABLES `wb_files` WRITE;
/*!40000 ALTER TABLE `wb_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `wb_files` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-11 21:27:25
