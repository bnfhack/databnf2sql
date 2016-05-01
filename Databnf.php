<?php
mb_internal_encoding ("UTF-8");
Databnf::persons();

class Databnf {
  /** Table de prénoms (pour reconnaître le sexe) */
  static $given;
  /** lien à la base de donnée */
  static $pdo;
  /** des compteurs */
  static $stats;
  static function connect($sqlfile, $create=false) {
    $dsn = "sqlite:" . $sqlfile;
    if($create && file_exists($sqlfile)) unlink($sqlfile);
    // create database
    if (!file_exists($sqlfile)) { // if base do no exists, create it
      if (!file_exists($dir = dirname($sqlfile))) {
        mkdir($dir, 0775, true);
        @chmod($dir, 0775);  // let @, if www-data is not owner but allowed to write
      }
      self::$pdo = new PDO($dsn);
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
      @chmod($sqlFile, 0775);
      self::$pdo->exec("
PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;
      ");
      return;
    }
    else {
      // echo getcwd() . '  ' . $dsn . "\n";
      // absolute path needed ?
      self::$pdo = new PDO($dsn);
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }
  }
  /**
   * Scanner les organisation auteur
   */
  public static function orgs() {
    Databnf::$stats=array("org"=>0);
    Databnf::scanglob("org/*_foaf_*.n3", Array("Databnf", "org"));
    echo Databnf::$stats['org']." orgs\n";
  }
  public static function org($filename) {
    fwrite(STDERR, $filename."\n");
    $res = fopen($filename, 'r');
    while (($line = fgets($res)) !== false) {
      $line = trim($line);
      if (preg_match('@#foaf:Organization> a foaf:Organization@', $line, $matches)) {
        Databnf::$stats['org']++;
      }
    }
  }
  static public function stats($glob) {
    self::$stats = array();
    $microtime = microtime(true);
    Databnf::scanglob($glob, Array("Databnf", "fstats"));
    echo (microtime(true) - $microtime) . " s.\n";
    foreach(self::$stats as $key=>$value) {
      echo $key . "\t" . $value . "\n";
    }
  }
  static public function fstats($filename) {
    fwrite(STDERR, $filename . "\n");
    $res = fopen($filename, 'r');
    while (($line = fgets($res)) !== false) {
      $line = trim($line);
      // try catch 7.58 mais message

      if (!$line);
      else if (strpos($line, 'a frbr:Work ;') !== false) {
        if(!isset(self::$stats['record'])) self::$stats['record'] = 1;
        else self::$stats['record']++;
      }
      else if ('<' == $line[0] || '@' == $line[0]);
      else if (preg_match('@^[^ :]+:[^ :]+@', $line, $matches)) {
        if (!isset(self::$stats[$matches[0]])) self::$stats[$matches[0]] = 1;
        else self::$stats[$matches[0]]++;
      }
    }
  }
  /**
   * Œuvres
   */
  static public function works() {
    Databnf::$stats = array();
    Databnf::connect("databnf.sqlite");
    self::$pdo->exec("
    DROP TABLE IF EXISTS work;
    CREATE TABLE work (
-- Œuvre
  identifier    TEXT NOT NULL,
  title         TEXT NOT NULL,
  date          TEXT,
  creator       TEXT,  -- lien à l’identifier sur la table des aut ?
  lang          TEXT,  -- texte, chansons films
  type          TEXT NOT NULL, -- text, sound, image, video
  dewey         INTEGER, --
  country       TEXT,
  description   TEXT
);
  ");
    Databnf::scanglob("works_2015-04/*_frbr_*.n3", Array("Databnf", "fworks"));
    self::$pdo->exec("
-- index
    ");
    arsort(self::$stats);
    print_r(self::$stats);
  }
  /**
   *
   */
  static public function fworks($filename) {
    fwrite(STDERR, $filename. "\n");
    $res = fopen($filename, 'r');
    $work = null;
    $cols = array("identifier", "title", "type", "creator", "description");
    $sql = "INSERT INTO work (".implode(", ", $cols).") VALUES (".rtrim(str_repeat("?, ", count($cols)), ", ").");";
    // self::$pdo->beginTransaction();
    $inswork = self::$pdo->prepare($sql);
    // de quoi ajouter le texte de la notice, pour debug
    $txt = array();
    $count = 0;
    while (($line = fgets($res)) !== false) {
      $line = trim($line);
      $line = preg_replace('/"@[a-z]+/', '', $line); // suppression des indications de langue
      // début d’œuvre, initialiser l’enregistreur
      if (!$work && preg_match('@^<http://data.bnf.fr/ark:/12148/([^/# ]+)#frbr:Work>@', $line, $matches)) {
        // un tableau d’exactement le nombre de cases que ce que l’on veut insérer
        $work = array_combine($cols, array_fill(0, count($cols), null));
        $work['identifier'] = $matches[1];
        $txt = array();
        $lastkey = null;
        $suf = null;
        $work['creator'] = array();
        $work['subject'] = array();
        continue;
      }
      // ici on laisse passer, théoriquement ligne vide entre deux œuvres
      if (!$work) continue;
      // capture de la clé
      preg_match('@^([^ :]+:[^ :]+)(.+)@', $line, $matches);
      if (isset($matches[1])) $key = $matches[1];
      else $key = null;
      if (isset($matches[2])) $value = trim($matches[2], "\" \t,.;");
      else $value = null;
      if (!$key && 'dcterms:description' == $lastkey) {
        $work['description'] .= trim($line, "\" \t,.;");
      }
      else if ('rdfs:label' == $key) { // inutile pour l’instant
        $label = $value;
        if (!$work['title']) $work['title'] = $value;
      }
      else if ('dcterms:title' == $key) {
        if (!$work['title']) $work['title'] = $value;
        $pos = strrpos($value, ':');
        if ($pos > 2) {
          $suf = trim(substr($value, $pos + 1));
          /*
          if ($work['type']);
          else if ("film" == $type) $work['type'] = 'video';
          */
        }
      }
      else if ('dcterms:creator' == $key) {
        $work['creator'][] = $value;
      }
      else if ('bnf-onto:subject' == $key) {
        $work['subject'][] = $value;
      }

      /* Attraper une date
    bnf-onto:firstYear 1984 ;
    bnf-onto:subject "Littératures" ;
    dcterms:creator <http://data.bnf.fr/ark:/12148/cb12060490k#foaf:Person> ;
    dcterms:date "1984" ;
    dcterms:description "Roman"@fr ;
    dcterms:language <http://id.loc.gov/vocabulary/iso639-2/spa> ;
    dcterms:subject <http://dewey.info/class/800/> ;
    dcterms:title "El desfile del amor"@es ;
    rdagroup1elements:dateOfWork <http://data.bnf.fr/date/1984/> .

    dcterms:description "Divertimento en 2 parties : \"Andantino\" et \"A minuet\""@fr ;
    description sur plusieurs lignes
       */

      $lastkey = $key;
      // fin d’assertion, insérer l’enregistrement
      if ($work && preg_match( "/\.$/", $line)) {
        $work['creator'] = implode($work['creator'], ' ; ');
        $work['subject'] = implode($work['subject'], '. ');
        if ('Audiovisuel' == $work['subject'] && $suf) {
          if (!isset(self::$stats['Audiovisuel'])) self::$stats['Audiovisuel'] = array();
          if (!isset(self::$stats['Audiovisuel'][$suf])) self::$stats['Audiovisuel'][$suf] = 1;
          else self::$stats['Audiovisuel'][$suf]++;
        }
        if ($work['subject'] && ('film' == $suf || 'série télévisée' == $suf || 'émission télévisée' == $suf)) {
          if (!isset(self::$stats['Vidéo'])) self::$stats['Vidéo'] = array();
          if (!isset(self::$stats['Vidéo'][$work['subject']] )) self::$stats['Vidéo'][$work['subject']] = 1;
          else self::$stats['Vidéo'][$work['subject']]++;
        }
        $work = null;
      }
    }
  }
  static public function persons() {
    include(dirname(__FILE__).'/given.php');
    Databnf::connect("databnf.sqlite");
    self::$pdo->exec("
    DROP TABLE IF EXISTS person;
    CREATE TABLE person (
  -- Autorité personne
  identifier  TEXT NOT NULL,
  name        TEXT NOT NULL,
  family      TEXT NOT NULL,
  given       TEXT,
  gender      INTEGER,

  birth       TEXT,
  death       TEXT,
  byear   INTEGER,
  dyear   INTEGER,
  age         INTEGER,
  birthplace  TEXT,
  deathplace  TEXT,

  lang        TEXT,
  country     TEXT,
  dewey       INTEGER,
  note        TEXT
);
  ");
    Databnf::scanglob("databnf_person_authors_n3/*_foaf_*.n3", Array("Databnf", "personsfile"));
    self::$pdo->exec("
CREATE INDEX person_identifier ON person(identifier);
CREATE INDEX person_family ON person(family);
CREATE INDEX person_given ON person(given);
CREATE INDEX person_gender ON person(gender);
CREATE INDEX person_birth ON person(birth);
CREATE INDEX person_death ON person(death);
CREATE INDEX person_age ON person(age);
CREATE INDEX person_byear ON person(byear);
CREATE INDEX person_dyear ON person(dyear);
CREATE INDEX person_birthplace ON person(birthplace);
CREATE INDEX person_deathplace ON person(deathplace);
CREATE INDEX person_lang ON person(lang);
CREATE INDEX person_country ON person(country);
CREATE INDEX person_dewey ON person(dewey);
    ");
  }
  public static function personsfile($filename)
  {
    fwrite(STDERR, $filename);
    $res = fopen($filename, 'r');
    $person = null;
    $cols = array("identifier", "name", "family", "given", "gender", "birth", "death", "byear", "dyear", "age", "birthplace", "deathplace", "lang", "country", "dewey", "note");
    $sql = "INSERT INTO person (".implode(", ", $cols).") VALUES (".rtrim(str_repeat("?, ", count($cols)), ", ").");";
    /*
    echo $sql;
    exit();
    */
    self::$pdo->beginTransaction();
    $insperson = self::$pdo->prepare($sql);
    // de quoi ajouter le texte de la notice, pour debug
    $txt = array();
    $count = 0;
    while (($line = fgets($res)) !== false) {
      $line = trim($line);
      // fin d’assertion, insérer l’auteur
      if ($person && preg_match( "/\.$/", $line)) {
        if(!$person['name'] && $person['family']) $person['name'] = trim ($person['given'] . " " . $person['family']);
        // gender
        if (!$person['gender'] && $person['given']) {
          $key = mb_strtolower($person['given']);
          $key = preg_split('@[ -]+@', $key)[0];
          if (isset(Databnf::$given[$key])) $person['gender'] = Databnf::$given[$key];
          // else echo $key."\n";
        }
        // age
        if ($person['byear'] && $person['dyear']) {
          $person['age'] = $person['dyear'] - $person['byear'];
          if ($person['age']<2 || $person['age']>115) { // erreur dans les dates, siècles sans point 1938-18=1920 ans
            $person['age']=$person['byear']=$person['dyear']=0;
          } // ne pas compter les enfants comme auteur
          if ($person['age'] < 20 ) $person['age'] = null;
        }
        /*
        if(count(array_values($person)) != 14) {
          echo "\n\n";
          echo implode("\n", $txt);
          print_r($person);
          print_r(array_values($person));
        }
        */
        try {
          $insperson->execute(array_values($person));
          $count++;
        }
        catch (Exception $e) {
          echo "\n\n\n\n".$e->getMessage()."\n";
          print_r($person);
          print_r(array_values($person));
          echo implode("\n", $txt);
        }
        $person = null;
        $txt = null;
      }
      // début d’auteur, initialiser l’enregistreur
      if (!$person && preg_match('@/([^/# ]+)#foaf:Person> a foaf:Person ;@', $line, $matches)) {
        $person = array_combine($cols, array_fill(0, count($cols), null));
        $person['identifier'] = $matches[1];
        $txt = array();
      }
      // ici on laisse passer
      else if (!$person);
      else if (preg_match('@foaf:name "([^"]+)"@', $line, $matches)) {
        $person['name'] = $matches[1];
      }
      else if (preg_match('@foaf:familyName "([^"]+)"@', $line, $matches)) {
        $person['family'] = $matches[1];
      }
      else if (preg_match('@foaf:givenName "([^"]+)"@', $line, $matches)) {
        $person['given'] = $matches[1];
      }
      else if (preg_match('@foaf:gender "([^"]+)"@', $line, $matches)) {
        if ($matches[1] == "male") $person['gender'] = 1;
        else if ($matches[1] == "female") $person['gender'] = 2;
        else echo "Gender ? ".$matches[1]."\n";
      }
      else if (preg_match('@bio:birth "(((- *)?[0-9\.]+)[^"]*)"@', $line, $matches)) {
        $person['birth'] = $matches[1];
        if (strpos($matches[2], '.') !== false);
        else if (is_numeric($matches[2])) $person['byear'] = $matches[2];
      }
      else if (preg_match('@bio:death "(((- *)?[0-9\.]+)[^"]*)"@', $line, $matches)) {
        $person['death'] = $matches[1];
        if (strpos($matches[2], '.') !== false);
        else if (is_numeric($matches[2])) $person['dyear'] = $matches[2];
      }
      // bnf-onto:firstYear 1919 ;
      // bnf-onto:lastYear 2006 ;
      else if (preg_match('@bnf-onto:firstYear[^0-9\-]*((- *)?[0-9\.]+)@', $line, $matches)) {
        if ($person['byear']); // la date en bio prend le dessus
        else if (strpos($matches[1], '.') !== false);
        else if (!is_numeric($matches[1]));
        else $person['byear'] = $matches[1];
      }
      else if (preg_match('@bnf-onto:lastYear[^0-9\-]*((- *)?[0-9\.]+)@', $line, $matches)) {
        if ($person['dyear']); // la date en bio prend le dessus
        else if (strpos($matches[1], '.') !== false);
        else if (!is_numeric($matches[1]));
        else $person['dyear'] = $matches[1];
      }
      else if (preg_match('@rdagroup2elements:placeOfBirth "([^"]+)"@', $line, $matches)) {
        $person['birthplace'] = $matches[1];
      }
      else if (preg_match('@rdagroup2elements:placeOfDeath "([^"]+)"@', $line, $matches)) {
        $person['deathplace'] = $matches[1];
      }
      else if (preg_match('@rdagroup2elements:countryAssociatedWithThePerson <http://id.loc.gov/vocabulary/countries/([^>/]+)@', $line, $matches)) {
        $person['country'] = $matches[1];
      }
      else if (preg_match('@rdagroup2elements:languageOfThePerson <http://id.loc.gov/vocabulary/iso639-2/([^>/]+)@', $line, $matches)) {
        $person['lang'] = $matches[1];
      }
      else if (preg_match('@rdagroup2elements:fieldOfActivityOfThePerson <http://dewey.info/class/([^>/]+)@', $line, $matches)) {
        $person['dewey'] = $matches[1];
      }
      // rdagroup2elements:biographicalInformation "
      else if (preg_match('@rdagroup2elements:biographicalInformation *"([^"]+)"@', $line, $matches)) {
        $person['note'] = $matches[1];
      }
      if (is_array($txt)) $txt[] = $line;
    }
    self::$pdo->commit();
    fwrite(STDERR, "  --  ".$count." persons\n");
  }


  static public function scanglob($srcglob, $function)
  {
    // scan files or folder, think to b*/*/*.xml
    foreach(glob($srcglob) as $srcfile) {
      if (is_dir($srcfile)) {
        self::scanglob($srcfile, $function);
      }
      $function($srcfile);
    }
    // glob sended is a single file, no recursion in subfolder, stop here
    if (isset($srcfile) && $srcglob == $srcfile) return;
    // continue scan in all subfolders, with the same file glob
    $pathinfo=pathinfo($srcglob);
    if (!$pathinfo['dirname']) $pathinfo['dirname']=".";
    foreach( glob( $pathinfo['dirname'].'/*', GLOB_ONLYDIR) as $srcdir) {
      $name=pathinfo($srcdir, PATHINFO_BASENAME);
      if ('_' == $name[0] || '.' == $name[0]) continue;
      self::scanglob($srcdir.'/'.$pathinfo['basename'], $function);
    }

  }
}


?>
