-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_daily_usage`
--

LOCK TABLES `api_daily_usage` WRITE;
/*!40000 ALTER TABLE `api_daily_usage` DISABLE KEYS */;
INSERT INTO `api_daily_usage` VALUES (1,'2025-08-10','meta-llama/llama-3.3-70b-instruct:free',1),(2,'2025-08-11','meta-llama/llama-3.3-70b-instruct:free',2),(4,'2025-08-11','deepseek/deepseek-chat-v3-0324:free',11),(15,'2025-08-23','deepseek/deepseek-chat-v3-0324:free',8);
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
) ENGINE=MyISAM AUTO_INCREMENT=258 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `difficult_words`
--

LOCK TABLES `difficult_words` WRITE;
/*!40000 ALTER TABLE `difficult_words` DISABLE KEYS */;
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
-- Table structure for table `kreml_k_presto_bednar_2025_05_28`
--

DROP TABLE IF EXISTS `kreml_k_presto_bednar_2025_05_28`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kreml_k_presto_bednar_2025_05_28` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kreml_k_presto_bednar_2025_05_28`
--

LOCK TABLES `kreml_k_presto_bednar_2025_05_28` WRITE;
/*!40000 ALTER TABLE `kreml_k_presto_bednar_2025_05_28` DISABLE KEYS */;
INSERT INTO `kreml_k_presto_bednar_2025_05_28` VALUES ('jako obvykle','as usual'),('to závisí','it depends'),('hledal jsem klíče a našel je','I looked for keys and then I found them'),('přišel jsem, abych ti pomohl','I came to help you'),('je snadné to udělat','it is easy to do'),('židle je na sezení','a chair is for sitting'),('rád bych se napil','I would like to drink.'),('potřebuji to opravit','I need to repair it');
/*!40000 ALTER TABLE `kreml_k_presto_bednar_2025_05_28` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_01`
--

DROP TABLE IF EXISTS `kremlik_bednar_01`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_01` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_01`
--

LOCK TABLES `kremlik_bednar_01` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_01` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_01` VALUES ('spolupracovník','colleague'),('uchazeč','applicant'),('veřejná knihovna','library'),('slovník','dictionary'),('slovní zásoba','vocabulary'),('kamarád, který…','a friend who…'),('položit otázku','ask a question'),('bitva','battle'),('medvěd','bear'),('menší','smaller'),('levnější','cheaper'),('srandovnější','funnier'),('Zavináč v mailové adrese','at'),('tečka v internetové adrese','dot'),('tři celých dva','three point two'),('farmaceutická společnost','pharmaceutical company'),('společnost byla založena','the company was founded'),('Švýcarsko','Switzerland'),('a tak dále','and so on'),('pobočka','a branch');
/*!40000 ALTER TABLE `kremlik_bednar_01` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_02`
--

DROP TABLE IF EXISTS `kremlik_bednar_02`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_02` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_02`
--

LOCK TABLES `kremlik_bednar_02` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_02` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_02` VALUES ('pobočka','a branch'),('v Evropě','in Europe'),('v České republice','in the Czech Republic'),('musím jít','I must go'),('začátek devadesátých let','early 90s'),('abychom se podívali na jejich problémy','to look at their problems'),('v pondělí','on Monday'),('trávit čas','spend time'),('faktura','invoice'),('může to fungovat','it can work'),('výskyt','incidence'),('úmrtnost','mortality'),('lék je předepšán na','the drug is indicated for…'),('léčba','treatment'),('onemocnění','condition'),('choroba','disease'),('podpůrná léčba','adjuvant'),('kardiovaskulární','cardiovascular'),('respirační','respiratory'),('kyselina hyaluronová','hyaluronic acid'),('lék na odkašlávání','mucolytic');
/*!40000 ALTER TABLE `kremlik_bednar_02` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_03`
--

DROP TABLE IF EXISTS `kremlik_bednar_03`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_03` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_03`
--

LOCK TABLES `kremlik_bednar_03` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_03` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_03` VALUES ('lék na odkašlávání','mucolytic'),('uro gynekologie','uro gynaecology'),('léčebné oblasti','thereapeutic areas'),('na kosti a klouby','osteoarticular'),('finanční obrat','turnover'),('dceřinná společnost','subsidiary'),('data jsou','the data is or are'),('zástupci','representatives'),('Italové žijí v Itálii','Italians live in Italy'),('dvě ženy','two women'),('Umím zpívat','I can sing'),('deka','blanket'),('lék','medicine'),('zánět','inflammation'),('největší producent','the biggest producer'),('podávat léky','to admiister medicine'),('dodržování norem','compliance of norms'),('vedoucí oddělení','head of the department'),('náčelník kmene','chief of the tribe');
/*!40000 ALTER TABLE `kremlik_bednar_03` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_04`
--

DROP TABLE IF EXISTS `kremlik_bednar_04`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_04` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_04`
--

LOCK TABLES `kremlik_bednar_04` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_04` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_04` VALUES ('děrné štítky','punch cards'),('zeptal se mě','he asked me'),('páté patro','fifth floor'),('pracoval jsem jako technik','I worked as a technician'),('výrobky','products'),('pokles výroby','decrease in production'),('dej tu fotku na web','post the photo on the web'),('Švýcarsko','Switzerland'),('řídit firmu','run a company'),('v práci řeším klienty a dodavatele','at work I deal with clients and suppliers'),('Jak se to jmenuje?','How is it called?'),('vodný roztok','aqueous solution'),('pohlavní styk','sexual intercourse'),('látky podporující růst pohlavních žláz','gonadotropins'),('s navázaným cukrem','glycosylated'),('neplodnost','infertility'),('nedostatečná funkce štítné žlázy','hypothyroid problems'),('filmová postava','film character'),('zajímám se o','I am interested in');
/*!40000 ALTER TABLE `kremlik_bednar_04` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_05`
--

DROP TABLE IF EXISTS `kremlik_bednar_05`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_05` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_05`
--

LOCK TABLES `kremlik_bednar_05` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_05` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_05` VALUES ('generální ředitel','CEO'),('vyžadovat','require'),('poslouchat hudbu','listen to music'),('mezi námi třemi','among the three of us'),('dávat rady','give advice'),('on je zodpovědný za','he is responsible for'),('zabývám se účty, řeším účty','I deal with accounts'),('specializuji se na to','I specialise in it'),('skládá se to z','it consists of'),('požádal jsem ho, aby pomohl','I asked him to help'),('v České republice','in the Czech Republic'),('hlavně','mainly'),('pracuji tu 15 let','I have been working here for 15 years'),('poškození','damage'),('vyskytovat s','occur'),('cíl','aim'),('zlepšit','improve'),('vydělávat','earn'),('přesná analýza','precise analysis'),('přístup, postoj','approach'),('muset','have to');
/*!40000 ALTER TABLE `kremlik_bednar_05` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_06`
--

DROP TABLE IF EXISTS `kremlik_bednar_06`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_06` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_06`
--

LOCK TABLES `kremlik_bednar_06` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_06` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_06` VALUES ('muset','have to'),('nesmíte tu kouřit','you mustn’t smoke here'),('v neděli nemusíte pracovat','you don’t have to work on Sunday'),('myslím, že ten film je zajímavý','I think the film is interesting.'),('přemýšlím o zítřku','I am thinking about tomorrow.'),('moje práce je konzultovat','my work is to consult'),('nyní','currently'),('přípravek','formulation'),('pot','sweat'),('poškození','damage'),('trpět něčím','suffer from'),('silná bolest','sever pain'),('způsobuje bolest','it causes pain'),('injekce','syringe'),('kapsle','capsule'),('zlepšit','improve'),('mezi dvěma','between two'),('mezi třemi','among three'),('poflakuju se s kamarády','I hang out with friends'),('kašlu na to','I don’t care'),('starám se o malé děti','take care of small children'),('pokračuj v práci','carry on working!'),('nést zavazadlo','carry luggage');
/*!40000 ALTER TABLE `kremlik_bednar_06` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_07`
--

DROP TABLE IF EXISTS `kremlik_bednar_07`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_07` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_07`
--

LOCK TABLES `kremlik_bednar_07` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_07` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_07` VALUES ('děláš si legraci?','are you kidding?'),('přestat kouřit','quit smoking'),('zánět močového měchýře','cystitis'),('intersticiální','interstitial'),('povrchová bariéra močového  traktu','urothelial'),('v květnu','in May'),('motýl','butterfly'),('v dubnu','in April'),('ještě jsem to neviděl','I haven’t seen it yet'),('mastné kyseliny','fatty acids'),('nevládní organizace','NGO, non governmental organisation'),('píseň od Elvise','a song by Elvis'),('udržitelný, ekologický','sustainable'),('chemické vlastnosti','chemical properties'),('hledal jsem to a našel','I was looking for it and I found it'),('naplňující povolání','a rewarding job'),('kulturní dědictví','cultural heritage'),('těším se, že se ozvete','I look forward to hearing from you'),('řekni promiň','say sorry'),('řekni mu to','tell him'),('natočeno Gibsonem','directed by Gibson'),('vrba','willow');
/*!40000 ALTER TABLE `kremlik_bednar_07` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_08`
--

DROP TABLE IF EXISTS `kremlik_bednar_08`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_08` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_08`
--

LOCK TABLES `kremlik_bednar_08` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_08` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_08` VALUES ('Trvá to hodinu','It takes 1 hour'),('přílety a odlety','arrivals and departures'),('dvacáté první století','Twenty-first century'),('musím to udělat','I must do it'),('starat se o dítě','Look after a child'),('Když hledám auto…','When I look for my car…'),('Nenašel jsem to','I did not find it.'),('bednář','cooper'),('tesař','carpenter'),('nastoupit do letadla, nalodit se','to board a plane'),('letoun','an aircraft'),('zapněte si pásy','fasten your seatbelts'),('příruční zavazadlo v letadle','hand luggage'),('box na zavazadla nad hlavou','overhead compartment'),('únava po letu do jiného časového pásma','jet lag'),('bezpečnou cestu!','safe journey!'),('kterým směrem?','which way?'),('personál','staff'),('protivný','annoying'),('hvízdat','whistle'),('pašovat','smuggle'),('tlačit, stisknout','push'),('jakmile budu připraven','as soon as I am ready');
/*!40000 ALTER TABLE `kremlik_bednar_08` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_09`
--

DROP TABLE IF EXISTS `kremlik_bednar_09`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_09` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_09`
--

LOCK TABLES `kremlik_bednar_09` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_09` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_09` VALUES ('bojím se','I am scared'),('ten film byl strašidelný','the film was scary'),('nejmenší dům','the smallest house'),('v Evropě','in Europe'),('je to trapas','it is embarrassing'),('jsou zmatení','they are confused'),('ještě ne','not yet'),('letět','fly, flew, flown'),('mám pravdu','I am right'),('je to pravda','it is true'),('Viděl jsi tohle někdy?','Have you ever seen it?'),('Je velmi sebejistý.','He is very confident.'),('Udělal jsem to omylem.','I did it by accident.'),('strašidelný film','scary movie'),('děsně špatný film','awful film'),('divný nechutný člověk','a creepy person'),('jděte rovně','go straight on'),('kruhový objezd','roundabout'),('semafor','traffic lights'),('ztratil jsem se','I got lost'),('zeptat se na cestu','ask for directions'),('zakladatel','founder'),('zraněný','injured');
/*!40000 ALTER TABLE `kremlik_bednar_09` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_10`
--

DROP TABLE IF EXISTS `kremlik_bednar_10`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_10` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_10`
--

LOCK TABLES `kremlik_bednar_10` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_10` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_10` VALUES ('přechod','zebra crossing'),('chodník','pavement'),('chodec','pedestrian'),('křižovatka','junction'),('brzda','brake'),('značka dopavní','road sign'),('předjíždět','overtake'),('auto skončilo v příkopu','the car ended up in a ditch'),('rychle strhnul volant','he swerved the car quickly'),('jako obvykle','as usual'),('to závisí','it depends'),('hledal jsem klíče a našel je','I looked for keys and then I found them'),('přišel jsem, abych ti pomohl','I came to help you'),('je snadné to udělat','it is easy to do'),('židle je na sezení','a chair is for sitting'),('rád bych se napil','I would like to drink.'),('potřebuji to opravit','I need to repair it');
/*!40000 ALTER TABLE `kremlik_bednar_10` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_20250526`
--

DROP TABLE IF EXISTS `kremlik_bednar_20250526`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_20250526` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_20250526`
--

LOCK TABLES `kremlik_bednar_20250526` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_20250526` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_20250526` VALUES ('jako obvykle','as usual'),('to závisí','it depends'),('hledal jsem klíče a našel je','I looked for keys and then I found them'),('přišel jsem, abych ti pomohl','I came to help you'),('je snadné to udělat','it is easy to do'),('židle je na sezení','a chair is for sitting'),('rád bych se napil','I would like to drink.'),('potřebuji to opravit','I need to repair it');
/*!40000 ALTER TABLE `kremlik_bednar_20250526` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_bednar_20250609`
--

DROP TABLE IF EXISTS `kremlik_bednar_20250609`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_bednar_20250609` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_bednar_20250609`
--

LOCK TABLES `kremlik_bednar_20250609` WRITE;
/*!40000 ALTER TABLE `kremlik_bednar_20250609` DISABLE KEYS */;
INSERT INTO `kremlik_bednar_20250609` VALUES ('jako obvykle','as usual'),('to závisí','it depends'),('hledal jsem klíče a našel je','I looked for keys and then I found them'),('přišel jsem, abych ti pomohl','I came to help you'),('je snadné to udělat','it is easy to do'),('židle je na sezení','a chair is for sitting'),('rád bych se napil','I would like to drink.'),('potřebuji to opravit','I need to repair it');
/*!40000 ALTER TABLE `kremlik_bednar_20250609` ENABLE KEYS */;
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
-- Table structure for table `kremlik_novak_slovicka1`
--

DROP TABLE IF EXISTS `kremlik_novak_slovicka1`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_novak_slovicka1` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_novak_slovicka1`
--

LOCK TABLES `kremlik_novak_slovicka1` WRITE;
/*!40000 ALTER TABLE `kremlik_novak_slovicka1` DISABLE KEYS */;
INSERT INTO `kremlik_novak_slovicka1` VALUES ('jako obvykle','as usual'),('to závisí','it depends'),('hledal jsem klíče a našel je','I looked for keys and then I found them'),('přišel jsem, abych ti pomohl','I came to help you'),('je snadné to udělat','it is easy to do'),('židle je na sezení','a chair is for sitting'),('rád bych se napil','I would like to drink.'),('potřebuji to opravit','I need to repair it');
/*!40000 ALTER TABLE `kremlik_novak_slovicka1` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_novak_slovicka5duben`
--

DROP TABLE IF EXISTS `kremlik_novak_slovicka5duben`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_novak_slovicka5duben` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_novak_slovicka5duben`
--

LOCK TABLES `kremlik_novak_slovicka5duben` WRITE;
/*!40000 ALTER TABLE `kremlik_novak_slovicka5duben` DISABLE KEYS */;
INSERT INTO `kremlik_novak_slovicka5duben` VALUES ('okno','das Fenster'),('podlaha','der Boden'),('střecha','das Dach'),('půdorys','der Grundgriss'),('sloup','die Säule'),('pomník','das Denkmal'),('jezero','der See'),('most','die Brücke'),('zámek','das Schloss'),('strom','tree'),('tráva','grass'),('písek','sand'),('země','ground'),('pěstovat','grow'),('plot','fence'),('zelenina','vegetables'),('ovoce','fruit');
/*!40000 ALTER TABLE `kremlik_novak_slovicka5duben` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_presto_bednar_20250609`
--

DROP TABLE IF EXISTS `kremlik_presto_bednar_20250609`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_presto_bednar_20250609` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_presto_bednar_20250609`
--

LOCK TABLES `kremlik_presto_bednar_20250609` WRITE;
/*!40000 ALTER TABLE `kremlik_presto_bednar_20250609` DISABLE KEYS */;
INSERT INTO `kremlik_presto_bednar_20250609` VALUES ('jako obvykle','as usual'),('to závisí','it depends'),('hledal jsem klíče a našel je','I looked for keys and then I found them'),('přišel jsem, abych ti pomohl','I came to help you'),('je snadné to udělat','it is easy to do'),('židle je na sezení','a chair is for sitting'),('rád bych se napil','I would like to drink.'),('potřebuji to opravit','I need to repair it');
/*!40000 ALTER TABLE `kremlik_presto_bednar_20250609` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kremlik_texts_forrestgump01`
--

DROP TABLE IF EXISTS `kremlik_texts_forrestgump01`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kremlik_texts_forrestgump01` (
  `czech` varchar(255) NOT NULL,
  `english` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kremlik_texts_forrestgump01`
--

LOCK TABLES `kremlik_texts_forrestgump01` WRITE;
/*!40000 ALTER TABLE `kremlik_texts_forrestgump01` DISABLE KEYS */;
INSERT INTO `kremlik_texts_forrestgump01` VALUES ('Myslí mi to dobře, ale když něco mám říct nebo napsat, někdy vyjdou úplně špatně.','I can think things OK, but when I have to say them or write them down, sometimes they come out all wrong.'),('Když jsem se narodil, maminka mi dala jméno Forrest.','When I was born, my Mom named me Forrest.'),('Můj tatínek zemřel hned po mém narození.','My daddy died just after I was born.'),('Pracoval na lodích.','He worked on the ships.'),('Jednoho dne na mého tátu spadla velká krabice banánů a zabila ho.','One day a big box of bananas fell down on my daddy and killed him.'),('Nemám moc rád banány.','I don\'t like bananas much.'),('Pouze banánový dort.','Only banana cake.'),('Ten mám docela rád.','I like that all right.'),('Zpočátku, když jsem vyrůstal, jsem si hrál se všemi.','At first when I was growing up, I played with everybody.'),('Ale pak mě nějací kluci zmlátili a moje máma už nechtěla, abych si s nimi hrál.','But then some boys hit me, and my Mom didn\'t want me to play with them again.'),('Snažil jsem se hrát si s holkama, ale všechny přede mnou utíkaly.','I tried to play with girls, but they all ran away from me.'),('Rok jsem chodil do běžné školy.','I went to an ordinary school for a year.'),('Pak se děti začaly smát a utíkat ode mě.','Then the children started laughing and running away from me.'),('Ale jedna dívka, Jenny Curranová, neutekla a někdy šla domů se mnou pěšky.','But one girl, Jenny Curran, didn\'t run away, and sometimes she walked home with me.'),('Byla milá.','She was nice.'),('Pak mě dali do jiného typu školy a tam byli nějací divní kluci.','Then they put me into another kind of school, and there were some strange boys there.'),('Někteří nemohli jíst ani jít na toaletu bez pomoci.','Some couldn\'t eat or go to the toilet without help.'),('V té škole jsem zůstal pět nebo šest let.','I stayed in that school for five or six years.'),('Ale když mi bylo třináct, vyrostl jsem za šest měsíců o šest centimetrů!','But when I was thirteen, I grew six inches in six months!');
/*!40000 ALTER TABLE `kremlik_texts_forrestgump01` ENABLE KEYS */;
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
/*!40000 ALTER TABLE `mastered_words` ENABLE KEYS */;
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
INSERT INTO `shared_tables` VALUES ('kremlik_languages_english','kremlik','2025-08-22 21:31:02'),('kremlik_languages_french','kremlik','2025-08-22 21:31:02'),('kremlik_languages_german','kremlik','2025-08-22 21:31:03'),('kremlik_languages_italian','kremlik','2025-08-22 21:31:03'),('kremlik_languages_spanish','kremlik','2025-08-22 21:31:03'),('quiz_choices_kremlik_languages_french3','kremlik','2025-08-22 22:55:22'),('quiz_choices_kremlik_languages_italian5','kremlik','2025-08-22 22:56:16'),('quiz_choices_kremlik_languages_spanish','kremlik','2025-08-22 22:56:16'),('victor_animal_domestic','victor','2025-08-22 21:45:56');
/*!40000 ALTER TABLE `shared_tables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shared_tables_private`
--

DROP TABLE IF EXISTS `shared_tables_private`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shared_tables_private` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(255) NOT NULL,
  `owner` varchar(64) NOT NULL,
  `target_username` varchar(64) DEFAULT NULL,
  `target_email` varchar(255) DEFAULT NULL,
  `shared_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_share` (`table_name`,`owner`,`target_username`,`target_email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shared_tables_private`
--

LOCK TABLES `shared_tables_private` WRITE;
/*!40000 ALTER TABLE `shared_tables_private` DISABLE KEYS */;
INSERT INTO `shared_tables_private` VALUES (1,'victor_animal_domestic','victor','Kremlik',NULL,'2025-08-22 22:12:19'),(2,'victor_animal_domestic','victor','dfdfd',NULL,'2025-08-22 22:12:37');
/*!40000 ALTER TABLE `shared_tables_private` ENABLE KEYS */;
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
-- Table structure for table `victor_animal_domestic`
--

DROP TABLE IF EXISTS `victor_animal_domestic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `victor_animal_domestic` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `victor_animal_domestic`
--

LOCK TABLES `victor_animal_domestic` WRITE;
/*!40000 ALTER TABLE `victor_animal_domestic` DISABLE KEYS */;
INSERT INTO `victor_animal_domestic` VALUES ('kočka','cat'),('pes','dog'),('kůň','horse'),('myš','mouse');
/*!40000 ALTER TABLE `victor_animal_domestic` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `victor_copycopy_animal_domestic`
--

DROP TABLE IF EXISTS `victor_copycopy_animal_domestic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `victor_copycopy_animal_domestic` (
  `Czech` varchar(255) NOT NULL,
  `English` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `victor_copycopy_animal_domestic`
--

LOCK TABLES `victor_copycopy_animal_domestic` WRITE;
/*!40000 ALTER TABLE `victor_copycopy_animal_domestic` DISABLE KEYS */;
INSERT INTO `victor_copycopy_animal_domestic` VALUES ('kočka','cat'),('pes','dog'),('kůň','horse'),('myš','mouse');
/*!40000 ALTER TABLE `victor_copycopy_animal_domestic` ENABLE KEYS */;
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

-- Dump completed on 2025-08-30 14:28:30
