<?php
/**
 * Classe un peu bricolée pour charger une base SQLite avec des données
 * DataBNF http://data.bnf.fr/semanticweb
 */
mb_internal_encoding ("UTF-8");
Databnf::connect("databnf.db");


Databnf::download();
Databnf::persons();
Databnf::documents();
Databnf::works();

class Databnf
{
  /** Table de prénoms (pour reconnaître le sexe) */
  static $given;
  /** Table de caractères */
  static $frtr;
  /** lien à la base de donnée */
  static $pdo;
  /** des requêtes préparées */
  static $q;
  /** des compteurs */
  static $stats;

  /**
   * Télécharger les données
   */
  static function download()
  {
    $files = array(
      "http://echanges.bnf.fr/PIVOT/databnf_editions_n3.tar.gz?user=databnf&password=databnf",
      "http://echanges.bnf.fr/PIVOT/databnf_person_authors_n3.tar.gz?user=databnf&password=databnf",
      "http://echanges.bnf.fr/PIVOT/databnf_org_authors_n3.tar.gz?user=databnf&password=databnf",
      "http://echanges.bnf.fr/PIVOT/databnf_works_n3.tar.gz?user=databnf&password=databnf",
      "http://echanges.bnf.fr/PIVOT/databnf_study_n3.tar.gz?user=databnf&password=databnf",
      "http://echanges.bnf.fr/PIVOT/databnf_periodics_n3.tar.gz?user=databnf&password=databnf",
    );
    foreach( $files as $src ) {
      $name = basename($src);
      if ( $pos=strpos($name, '?') ) $name = substr( $name, 0, $pos );
      $arc = dirname(__FILE__).'/'.$name;
      echo $name;
      if ( !file_exists($arc) ) {
        echo " …téléchargement… ";
        copy( $src, $arc );
      }
      preg_match( '@databnf_([^_]+)_@', $name, $matches);
      $dir = dirname(__FILE__).'/'.$matches[1].'/';
      if ( !file_exists($dir) ) {
        echo " …décompression… ";
        mkdir( $dir );
        // pas compatible windows
        $cmd = 'tar -zxf '.$arc." -C ".$dir;
        echo "\n".$cmd."\n";
        passthru( $cmd );
      }
      echo " OK\n";
    }
    // tar -zxvf
  }

  /**
   * Connexion à la base de données
   */
  static function connect($sqlfile, $create=false)
  {
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
      @chmod($sqlfile, 0775);
      self::$pdo->exec( file_get_contents( dirname(__FILE__)."/databnf.sql" ) );
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
  public static function orgs()
  {
    Databnf::$stats=array("org"=>0);
    Databnf::scanglob("org/*_foaf_*.n3", Array("Databnf", "org"));
    echo Databnf::$stats['org']." orgs\n";
  }

  /**
   *
   */
  public static function org($filename)
  {
    fwrite(STDERR, $filename."\n");
    $res = fopen($filename, 'r');
    while (($line = fgets($res)) !== false) {
      $line = trim($line);
      if (preg_match('@#foaf:Organization> a foaf:Organization@', $line, $matches)) {
        Databnf::$stats['org']++;
      }
    }
  }

  /**
   *
   */
  static public function stats($glob)
  {
    self::$stats = array();
    $microtime = microtime(true);
    Databnf::scanglob($glob, Array("Databnf", "fstats"));
    echo (microtime(true) - $microtime) . " s.\n";
    foreach(self::$stats as $key=>$value) {
      echo $key . "\t" . $value . "\n";
    }
  }

  /**
   *
   */
  static public function fstats($filename)
  {
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
   * Charger tous les documents dans la base
   */
  static public function documents()
  {
    self::$pdo->beginTransaction();
    self::$q['doc'] = self::$pdo->prepare( "INSERT INTO document ( code, title, date, place ) VALUES ( ?, ?, ?, ? )" );
    self::$q['version'] = self::$pdo->prepare( "INSERT INTO version ( documentC, workC ) VALUES ( ?, ? )" );
    self::manif();
    // on commite une première fois pour avoir la clé sur la cote à mettre à jour
    self::$pdo->commit();

    self::$pdo->beginTransaction();
    self::$q['docup'] = self::$pdo->prepare( "UPDATE document SET lang = ?, type = ? WHERE code = ? " );
    self::expr();
    self::$pdo->commit();
    self::$pdo->exec( "UPDATE document SET lang='' WHERE lang IS NULL" );
  }

  /**
   * Charger une notice
   */
  static public function manifSql( $record )
  {
    try {
      self::$q['doc']->execute( array( $record['code'], $record['title'], $record['date'], $record['place'] ) );
    }
    catch( Exception $e ) {
      // si pièce d’archive, normal
      if ( strpos( $record['code'], "cc" ) === 0 );
      else
        echo "  Doublon ? ".$record['code']." ".$record['title']."\n";
    }
    if ( isset( $record['work'] ) && is_array( $record['work'] ) ) {
      foreach ( $record['work'] as $k=>$v ) {
        self::$q['version']->execute( array( $record['code'], $k ) );
      }
    }
  }

  /**
   * Charger les notices de documents
   *
   * <http://data.bnf.fr/ark:/12148/cb39605922n> a frbr:Manifestation ;
   * bnf-onto:FRBNF 39605922 ;
   * bnf-onto:firstYear 1993 ;
   * dcterms:date "cop. 1993" ;
   * dcterms:description "1 partition (3 p.) : 30 cm" ;
   * dcterms:publisher "Lyon : A coeur joie , cop. 1993" ;
   * dcterms:subject <http://data.bnf.fr/ark:/12148/cb119329384>,
   *    <http://data.bnf.fr/ark:/12148/cb11975995h>,
   *    <http://data.bnf.fr/ark:/12148/cb14623720f> ;
   * dcterms:title "La blanche neige : [choeur] à 3 voix égales a cappella ou avec accompagnement de piano ad libitum" ;
   * rdagroup1elements:dateOfPublicationManifestation <http://data.bnf.fr/date/1993/> ;
   * rdagroup1elements:note "Note : Titre général : \"Six choeurs de Guillaume Apollinaire, extraits de Alcools\" ; 1. - Durée : 2'30" ;
   * rdagroup1elements:placeOfPublication "Lyon" ;
   * rdagroup1elements:publishersName "A coeur joie" ;
   * rdarelationships:expressionManifested <http://data.bnf.fr/ark:/12148/cb39605922n#frbr:Expression> ;
   * rdarelationships:workManifested <http://data.bnf.fr/ark:/12148/cb13912399j#frbr:Work>,
   *   <http://data.bnf.fr/ark:/12148/cb139124107#frbr:Work>,
   *   <http://data.bnf.fr/ark:/12148/cb14017380x#frbr:Work> ;
   *   rdfs:seeAlso <http://catalogue.bnf.fr/ark:/12148/cb39605922n> ;
   * = <http://data.bnf.fr/ark:/12148/cb39605922n#about> .
   *
   */
  static public function manif( $function="Databnf::manifSql" )
  {
    $glob = dirname(__FILE__).'/editions/databnf_editions__manif_*.n3';
    echo $glob."\n";
    self::glob( $glob, $function );
  }
  /**
   * Boncler sur les fichiers "editions__expr" pour ramasser
   * des métadonnées de document (langue, type, sujets)
   *
   * <http://data.bnf.fr/ark:/12148/cb39605922n#frbr:Expression> a frbr:Expression ;
   *   dcterms:language <http://id.loc.gov/vocabulary/iso639-2/fre> ;
   *   dcterms:subject <http://data.bnf.fr/ark:/12148/cb119329384>,
   *     <http://data.bnf.fr/ark:/12148/cb11975995h>,
   *     <http://data.bnf.fr/ark:/12148/cb14623720f> ;
   *    dcterms:type dcmitype:Text ;
   *  = <http://data.bnf.fr/ark:/12148/cb39605922n#Expression> .
   */
  static public function expr( $function="Databnf::exprSql" ) {
    // Traverser les expressions pour ramasser quelques autres étadonnées (langue ? type ?)
    $glob = dirname(__FILE__).'/editions/databnf_editions__expr_*.n3';
    echo $glob."\n";
    self::glob( $glob, $function );
  }
  /**
   * Update d’un document avec des propriétés supplémentaires obtenues des fichiers expr*
   * lang, type, subject
   */
  static public function exprSql( $record ) {
    $type = $record['type'];
    if ( isset($record['subject']['cb119329384']) ) $type = "Score";
    else if ( strpos( $record['code'], 'cc' ) === 0 ) $type = "Archive";
    self::$q["docup"]->execute( array( $record['language'], $type, $record['code'] ) );
  }
  /**
   *  Normalisation des enregistrements
   *
   */
  static function recnorm( $record )
  {
    // lien à une œuvre
    if ( isset( $record["workManifested"] ) ) {
      preg_match_all( "@<http://data.bnf.fr/ark:/12148/([^#]+)#frbr:Work>@", $record["workManifested"], $match_work );
      $record['work'] = array_flip( $match_work[1] );
    }

    // date
    if ( ! isset( $record["firstYear"] ) )
      $record['date']=null;
    else if (preg_match( "@(-?[0-9][0-9][0-9][0-9])@", $record["firstYear"], $match_date ))
      $record['date'] = $match_date[1];
    else
      $record['date']=null;

    // titre
    if ( isset( $record["title"] ) ) {
      // Attention aux guillemets dans les titres : Apollinaire et la "Démocratie sociale
      $record["title"] = stripslashes(
        preg_replace(
          '/^"|" *;?$/',
          '',
          trim( $record["title"] )
        )
      );
    } else $record["title"] = null;

    // langue
    if ( isset($record["language"]) ) {
      preg_match( "@<http://id.loc.gov/vocabulary/iso639-2/([^>]+)>@", $record["language"], $match_lang );
      $record['language'] = $match_lang[1];
    } else $record['language'] = null;

    // type de documents
    if ( isset( $record["type"] ) ) {
      preg_match( "@dcmitype:([^ ]+)@", $record["type"], $match_type );
      // un seul type ? le dernier ?
      $record['type'] = $match_type[1];
    } else $record['type'] = null;

    // indexation sujet
    if ( isset( $record["subject"] ) ) {
      preg_match_all( "@<http://data.bnf.fr/ark:/12148/([^>]+)>@", $record["subject"], $match_subject );
      // prendre les sujets comme clés de hashmap
      $record['subject'] = array_flip( $match_subject[1] );
    } else $record["subject"] = array();

    // lieu de publication
    if ( isset( $record["placeOfPublication"] ) ) {
      if ( $pos = strpos( $record["placeOfPublication"], ':') ) $record["placeOfPublication"] = substr( $record["placeOfPublication"], 0, $pos);
      if ( $pos = strpos( $record["placeOfPublication"], ' (') ) $record["placeOfPublication"] = substr( $record["placeOfPublication"], 0, $pos);
      if ( $pos = strpos( $record["placeOfPublication"], ',') ) $record["placeOfPublication"] = substr( $record["placeOfPublication"], 0, $pos);
      if ( $pos = strpos( $record["placeOfPublication"], '.') ) $record["placeOfPublication"] = substr( $record["placeOfPublication"], 0, $pos);
      if ( $pos = strpos( $record["placeOfPublication"], ' et') ) $record["placeOfPublication"] = substr( $record["placeOfPublication"], 0, $pos);
      $record['place'] = trim( $record["placeOfPublication"], ' ";()[]');
    } else $record['place'] = null;

    return $record;
  }

  /**
   *  Tourner sur une liste de fichiers
   *  en retirer un enregistrement normalisé
   *  déléguer le traitement de l’enregistrement à une fonction
   */
  static function parse( $glob, $function )
  {
    foreach( glob( $glob ) as $filepath ) {
      $filename = basename($filepath);
      fwrite(STDERR, $filename."\n");
      $filestream = fopen( $filepath, "r" );
      $key = '';
      $value = '';
      $record = array();
      while ( ( $line = fgets( $filestream ) ) !== false) {
        $line = trim($line);
        // fin d’un enregistrement, enregistrer
        if ( preg_match( '@= <http://data.bnf.fr/ark:/12148/([^#>]+)(#[^>]+)> \.@', $line, $matches ) ) {
          $record[ $key ] = $value; // denière propriété en suspens
          $record = self::recnorm( $record );
          call_user_func( $function, $record );
          $record = null;
        }
        // debut d’un enregistrement qui nous intéresse
        else if ( preg_match( '@<http://data.bnf.fr/ark:/12148/([^#>]+)(#[^>]+)?> a [^ ]+ ;@', $line, $match_id ) ) {
          $record = array( );
          $record['code'] = $match_id[1];
          $key = "";
          $value = "";
        }
        // pas encore d’enregistrement
        else if ( !$record );
        // début d’une propriété
        else if ( preg_match( '@[a-z\-]+:([a-zA-Z\-]+) (.+)@', $line, $match_kv ) ) {
          if ( $key ) $record[ $key ] = $value;
          $key = $match_kv[1];
          $value = $match_kv[2];
        }
        // ajouter à la valeur courante
        else {
          $value .= $line;
        }
      }
    }
  }
  /**
   *
   * <http://data.bnf.fr/ark:/12148/cb32726206b#frbr:Work> a frbr:Work ;
   *  rdfs:label "Bulletin des Ingénieurs des Arts et métiers de la Fédération des Groupes Alpes dauphinoises, Savoie, Haute-Savoie,  Drôme, Ardèche" ;
   *  bnf-onto:firstYear 1848 ;
   *  bnf-onto:lastYear 1865 ;
   *  dcterms:created "19.." ;
   *  dcterms:description "In-8" ;
   *  dcterms:language <http://id.loc.gov/vocabulary/iso639-2/fre> ;
   *  dcterms:publisher "Grenoble : [s.n.] , [19..-19..]" ;
   *  dcterms:title "Bulletin des Ingénieurs des Arts et métiers de la Fédération des Groupes Alpes dauphinoises, Savoie, Haute-Savoie, Drôme, Ardèche"@fr ;
   *  bibo:issn "2428-1719" ;
   *  rdagroup1elements:note "La couverture porte : \"Alpes Gadz'arts. Bulletin des ingénieurs...\""@fr ;
   *  rdagroup1elements:placeOfPublication "Grenoble" ;
   *  rdagroup1elements:publishersName "[s.n.]" ;
   *  = <http://data.bnf.fr/ark:/12148/cb32726206b#about> .
   */
   static function periodics(  )
   {

   }
   static function contributions()
   {
     $glob = dirname(__FILE__).'/person/databnf_person_authors__contributions_*.n3';
     echo $glob."\n";
     foreach( glob( $glob ) as $filepath ) {
       self::contributions( $filepath );
     }
   }
  /**
   * databnf_person_authors__contributions_*.n3
   *
   * <http://data.bnf.fr/ark:/12148/cb43068229b#Expression> bnfroles:r220 <http://data.bnf.fr/ark:/12148/cb10221320m#foaf:Person> ;
   *   bnfroles:r70 <http://data.bnf.fr/ark:/12148/cb10221320m#foaf:Person> ;
   *   marcrel:aut <http://data.bnf.fr/ark:/12148/cb10221320m#foaf:Person> ;
   *   marcrel:cmp <http://data.bnf.fr/ark:/12148/cb10221320m#foaf:Person> ;
   *   dcterms:contributor <http://data.bnf.fr/ark:/12148/cb10221320m#foaf:Person> .
   *
   * Relation entre une personne auteur et un document.
   * En général il n’y a qu’une relation, exemple : auteur (70)
   * Il y a une ontologie des rôles.
   *
   */
   static function fcontributions( $filepath )
   {
     self::$pdo->beginTransaction();
     $q = self::$pdo->prepare( "INSERT INTO contribution ( role, document, person, documentC, personC ) VALUES ( ?, (SELECT id FROM document WHERE code = ?), ?, ?, ? )" );
     fwrite(STDERR, basename($filepath)."\n");
     $filestream = fopen( $filepath, "r" );
     $record = array();
     while ( ( $line = fgets( $filestream ) ) !== false) {
       if ( !count($record) );
       // ligne blanche, traitement
       else if ( !trim( $line ) ) {
         $q->execute( array(
           $record['bnfrole'],
           $record['document'],
           self::code2id( $record['person'] ),
           $record['document'],
           $record['person'],
         ) );
         $record = array();
         continue;
       }
       $re = "@<http://data.bnf.fr/ark:/12148/([^#]+)#Expression> bnfroles:r([0-9]+) <http://data.bnf.fr/ark:/12148/([^#]+)#foaf:Person>@";
       if ( preg_match( $re, $line, $matches ) ) {
         $record['document'] = $matches[1];
         $record['bnfrole'] = $matches[2];
         $record['person'] = $matches[3];
       }
     }
     fclose( $filestream );
     self::$pdo->commit();
   }



   /**
    * Œuvres
    */
   static public function works()
   {
     $glob = "works/*_frbr_*.n3";
     foreach( glob( $glob ) as $filepath ) {
       self::fworks( $filepath );
     }
     self::$pdo->exec( "UPDATE version SET document=(SELECT rowid FROM document WHERE code=documentC)" );
     self::$pdo->exec( "UPDATE version SET work=(SELECT rowid FROM work WHERE code=workC)" );
     self::$pdo->exec( "UPDATE work SET versions=(SELECT count(*) FROM version WHERE work=work.id AND date > 0)" );
   }

  /**
   * Chargement d’une liste d’œuvres
   */
  static public function fworks( $filename ) {
    fwrite(STDERR, $filename. "\n");
    $res = fopen($filename, 'r');
    $work = null;
    $cols = array( "id", "code", "title", "date", "lang" );
    $sql = "INSERT INTO work (".implode(", ", $cols).") VALUES (".rtrim(str_repeat("?, ", count($cols)), ", ").");";
    // self::$pdo->beginTransaction();
    $q = self::$pdo->prepare($sql);
    $q2 = self::$pdo->prepare(
      "INSERT INTO creation ( workC, work, personC, person ) VALUES ( ?, ?, ?, ( SELECT id FROM person WHERE code = ? ));"
    );
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
        $work['code'] = $matches[1];
        $work['id'] = self::code2id( $work['code'] );
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
        if ( !isset($work['description']) ) $work['description'] = "";
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
      else if ( 'dcterms:language' == $key ) {
        preg_match( "@<http://id.loc.gov/vocabulary/iso639-2/([^>]+)>@", $value, $match_lang );
        $work['lang'] = $match_lang[1];
      }
      else if ( 'bnf-onto:firstYear' == $key ) {
        $work['date'] = 0+trim($value);
        if ( !$work['date'] ) $work['date'] = null;
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
        // $work['creator'] = implode($work['creator'], ' ; ');
        $work['subject'] = implode($work['subject'], '. ');
        if ( count( $work['creator'] ) ) {
          foreach( $work['creator'] as $value ) {
            if ( preg_match( "@<http://data.bnf.fr/ark:/12148/([^#]+)#foaf:Person>@", $value, $match_pers ) ) {
              $q2->execute( array( $work['code'], $work['id'], $match_pers[1], $match_pers[1] ) );
            }
          }
        }
        $record = array( $work['id'], $work['code'], $work['title'], $work['date'], $work['lang'] );
        try {
          $q->execute( $record );
        } catch ( Exception $e ) {
          print_r( $e );
          print_r( $record );
          exit();
        }
        $work = null;
      }
    }
  }
  /**
   * Chargement des personnes
   * Pas encore de prise en compte des homonymes
   */
  static public function persons() {
    include(dirname(__FILE__).'/given.php');
    self::$given = $given;
    include( dirname( __FILE__ )."/frtr.php" ); // crée une variable $frtr
    self::$frtr = $frtr;
    $glob = dirname( __FILE__ )."/person/databnf_person_authors__foaf_*.n3";
    foreach( glob( $glob ) as $file ) {
      self::catfoaf( $file );
    }
    self::$pdo->exec( "UPDATE person SET docs=(SELECT count(*) FROM contribution WHERE person=person.id AND role IN (70, 71, 72, 73, 980, 990, 4020) );" );
  }
  /**
   * Traitement d’un fichier de personnes
   */
  public static function catfoaf( $file )
  {
    fwrite( STDERR, basename($file) );
    $res = fopen( $file, 'r');
    $person = null;
    $cols = array("id", "code", "sort", "name", "family", "given", "gender", "birth", "death", "birthyear", "deathyear", "birthplace", "deathplace", "age", "lang", "country", "note");
    $sql = "INSERT INTO person (".implode(", ", $cols).") VALUES (".rtrim(str_repeat("?, ", count($cols)), ", ").");";
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
          $key = reset( preg_split('@[ -]+@', $key) );
          if (isset(Databnf::$given[$key])) $person['gender'] = Databnf::$given[$key];
          // else echo $key."\n";
        }
        // age
        if ($person['birthyear'] && $person['deathyear']) {
          $person['age'] = $person['deathyear'] - $person['birthyear'];
          if ($person['age']<2 || $person['age']>115) { // erreur dans les dates, siècles sans point 1938-18=1920 ans
            $person['age']=$person['birthyear']=$person['deathyear']=0;
          } // ne pas compter les enfants comme auteur
          if ($person['age'] < 20 ) $person['age'] = null;
        }
        $person['sort'] = strtr( $person['family'].$person['given'], self::$frtr );
        $person['id'] = self::code2id( $person['code'] );
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
          echo "\n".$e->getMessage()."\n";
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
        $person['code'] = $matches[1];
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
        else if (is_numeric($matches[2])) $person['birthyear'] = $matches[2];
      }
      else if (preg_match('@bio:death "(((- *)?[0-9\.]+)[^"]*)"@', $line, $matches)) {
        $person['death'] = $matches[1];
        if (strpos($matches[2], '.') !== false);
        else if (is_numeric($matches[2])) $person['deathyear'] = $matches[2];
      }
      // bnf-onto:firstYear 1919 ;
      // bnf-onto:lastYear 2006 ;
      else if (preg_match('@bnf-onto:firstYear[^0-9\-]*((- *)?[0-9\.]+)@', $line, $matches)) {
        if ($person['birthyear']); // la date en bio prend le dessus
        else if (strpos($matches[1], '.') !== false);
        else if (!is_numeric($matches[1]));
        else $person['birthyear'] = $matches[1];
      }
      else if (preg_match('@bnf-onto:lastYear[^0-9\-]*((- *)?[0-9\.]+)@', $line, $matches)) {
        if ($person['deathyear']); // la date en bio prend le dessus
        else if (strpos($matches[1], '.') !== false);
        else if (!is_numeric($matches[1]));
        else $person['deathyear'] = $matches[1];
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
        // n’est plus attendu
        // $person['dewey'] = $matches[1];
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
  /**
   * Les identifiants BNF sont normalement sûrs, voyons
   */
   public static function code2id( $cote ) {
     return 0+substr($cote, 2, -1);
   }

}


?>
