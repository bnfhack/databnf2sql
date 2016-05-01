<?php

/*
Questions pour mieux comprendre les données
 * Combien de manifestations pour une expression ?
 *

*/

mb_internal_encoding ("UTF-8");
// GraphBNF::contributions();
// cb11888978p, Apollinaire
// GraphBNF::scan( "cb30364810m" );

$expressions = GraphBNF::contributions( "cb11888978p" );
// expressions uniques, vérifiées
$relations = GraphBNF::contributions( null, $expressions );
$writer = fopen( 'apollinaire_edges.tsv', "w" );
fwrite( $writer, "Source\tTarget\trole\n");
$authors = array();
foreach( $relations as $rel ) {
  // collecter les nœuds avce un hash pour unifier
  $authors[$rel[0]] = true;
  fwrite( $writer, $rel[0]);
  fwrite( $writer, "\t");
  fwrite( $writer, $rel[1]);
  fwrite( $writer, "\t");
  fwrite( $writer, $rel[2]);
  fwrite( $writer, "\n");
}
fclose( $writer );

$writer = fopen( 'apollinaire_nodes.tsv', "w" );
fwrite( $writer, "Id\tLabel\ttype\tdate\n");
GraphBNF::connect();
$q = GraphBNF::$pdo->prepare("SELECT * FROM person WHERE identifier = ?; ");
foreach( $authors as $identifier => $v ) {
  $q->execute( array( $identifier ) );
  $row = $q->fetch(PDO::FETCH_ASSOC);
  $name = $row['family'];
  if ( $row['family'] && $row['given'] ) $name .= ', '.$row['given'];
  fwrite( $writer, $identifier );
  fwrite( $writer, "\t" );
  fwrite( $writer, $name );
  fwrite( $writer, "\t" );
  fwrite( $writer, "author" );
  fwrite( $writer, "\n" );
}
// ici écrire les œuvres
$manifestations = GraphBNF::manifestations( $expressions );
print_r( $manifestations );
foreach( $manifestations as $identifier => $row ) {
  fwrite( $writer, $identifier );
  fwrite( $writer, "\t" );
  fwrite( $writer, $row['title'] );
  fwrite( $writer, "\t" );
  fwrite( $writer, "work" );
  fwrite( $writer, "\t" );
  fwrite( $writer, "date" );
  fwrite( $writer, "\n" );

}
fclose( $writer );



/*

Avec les identifiants d’expressions
rechercher :
* un titre
* une date
* un type

Avec un identifiant d’auteur, parcourir les études, aller rechercher

 */
class GraphBNF {
  static $pdo;

  static function connect() {
    $dsn = "sqlite:".dirname(__FILE__).'/databnf.sqlite';
    self::$pdo = new PDO($dsn);
    self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  }

  /**
   *
   */
  function works( $id )
  {
    $ret = array();
    $dir = dirname(__FILE__).'/databnf_works_n3/';
    foreach( glob( $dir.'databnf_person_authors__contributions_*.n3' ) as $filepath ) {

    }
  }

  /**
   * Traitement d’une liste d’expressions
   * En recueillir les métadonnées
   * databnf_editions__expr_*.n3
   *  – langue
   *  – forme (?)
   * databnf_editions__manif_*.n3
   *  — titre
   *  – date
   *  –
   */
  function manifestations( $expressions )
  {
    $expressions = array_flip( $expressions );
    // collecter les manifestations
    $glob = dirname(__FILE__).'/databnf_editions_n3/databnf_editions__manif_*.n3';
    foreach( glob( $glob ) as $filepath ) {
      $filename = basename($filepath);
      fwrite(STDERR, $filename."\n");
      $filestream = fopen( $filepath, "r" );
      $key = '';
      $value = '';
      $record = array();
      while ( ( $line = fgets( $filestream ) ) !== false) {
        $line = trim($line);
        /*
        <http://data.bnf.fr/ark:/12148/cb39605922n> a frbr:Manifestation ;
        bnf-onto:FRBNF 39605922 ;
        bnf-onto:firstYear 1993 ;
        dcterms:date "cop. 1993" ;
        dcterms:description "1 partition (3 p.) : 30 cm" ;
        dcterms:publisher "Lyon : A coeur joie , cop. 1993" ;
        dcterms:subject <http://data.bnf.fr/ark:/12148/cb119329384>,
            <http://data.bnf.fr/ark:/12148/cb11975995h>,
            <http://data.bnf.fr/ark:/12148/cb14623720f> ;
        dcterms:title "La blanche neige : [choeur] à 3 voix égales a cappella ou avec accompagnement de piano ad libitum" ;
        rdagroup1elements:dateOfPublicationManifestation <http://data.bnf.fr/date/1993/> ;
        rdagroup1elements:note "Note : Titre général : \"Six choeurs de Guillaume Apollinaire, extraits de Alcools\" ; 1. - Durée : 2'30" ;
        rdagroup1elements:placeOfPublication "Lyon" ;
        rdagroup1elements:publishersName "A coeur joie" ;
        rdarelationships:expressionManifested <http://data.bnf.fr/ark:/12148/cb39605922n#frbr:Expression> ;
        rdarelationships:workManifested <http://data.bnf.fr/ark:/12148/cb13912399j#frbr:Work>,
          <http://data.bnf.fr/ark:/12148/cb139124107#frbr:Work>,
          <http://data.bnf.fr/ark:/12148/cb14017380x#frbr:Work> ;
        rdfs:seeAlso <http://catalogue.bnf.fr/ark:/12148/cb39605922n> ;
        = <http://data.bnf.fr/ark:/12148/cb39605922n#about> .
        */
        // fin d’un enregistrement
        if ( preg_match( '@= <http://data.bnf.fr/ark:/12148/[^#]+#about> \.@', $line, $matches ) ) {
          if ( !count( $record ) ) exit( $line. " fin de Record avant début ?" );
          if ( isset( $expressions[$record['id']] ) )  {
            $expressions[$record['id']] = $record;
          }
          $record = null;
        }
        // debut d’un enregistrement qui nous intéresse
        else if ( preg_match(  '@<http://data.bnf.fr/ark:/12148/([^>]+)> a frbr:Manifestation@', $line, $matches ) ) {
          $record = array();
          $record['id'] = $matches[1];
          $key = "";
          $value = "";
        }
        // pas encore d’enregistrement
        else if ( !$record );
        // début d’une propriété
        else if ( preg_match( '@[a-z\-]+:([a-zA-Z\-]+) (.+)@', $line, $match_kv ) ) {
          // enregistrement de la propriété précédente
          if ( $key && $value ) {
            if ( "workManifested" == $key ) {
              preg_match_all( "@<http://data.bnf.fr/ark:/12148/([^#]+)#frbr:Work>@", $value, $match_work );
              $record['work'] = $match_work[1];
            }
            else if ( "title" == $key ) {
              $record['title'] = stripslashes( trim( $value, ' ";') );
            }
            else if ( "firstYear" == $key ) {
              $record['date'] = trim( $value, ' ;');
            }
            else if ( "placeOfPublication" == $key ) {
              if ( $pos = strpos( $value, ':') ) $value = substr( $value, $pos);
              if ( $pos = strpos( $value, ',') ) $value = substr( $value, $pos);
              if ( $pos = strpos( $value, '.') ) $value = substr( $value, $pos);
              $record['place'] = trim( $value, ' ";()[]');
            }
          }
          $key = $match_kv[1];
          $value = $match_kv[2];
        }
        // ajouter à la valeur courante
        else {
          $value .= $line;
        }
      }
    }
    // Traverser les expressions pour ramasser quelques autres étadonnées (langue ? type ?)
    $glob = dirname(__FILE__).'/databnf_editions_n3/databnf_editions__expr_*.n3';
    foreach( glob( $glob ) as $filepath ) {
      $filename = basename($filepath);
      fwrite(STDERR, $filename."\n");
      $filestream = fopen( $filepath, "r" );
      $key = '';
      $value = '';
      $record = array();
      while ( ( $line = fgets( $filestream ) ) !== false) {
        $line = trim($line);
        /*
        <http://data.bnf.fr/ark:/12148/cb39605922n#frbr:Expression> a frbr:Expression ;
        dcterms:language <http://id.loc.gov/vocabulary/iso639-2/fre> ;
        dcterms:subject <http://data.bnf.fr/ark:/12148/cb119329384>,
            <http://data.bnf.fr/ark:/12148/cb11975995h>,
            <http://data.bnf.fr/ark:/12148/cb14623720f> ;
        dcterms:type dcmitype:Text ;
        = <http://data.bnf.fr/ark:/12148/cb39605922n#Expression> .
        */
        // fin d’un enregistrement, que faire ?
        if ( preg_match( '@= <http://data.bnf.fr/ark:/12148/[^#]+#Expression> \.@', $line, $matches ) ) {
          if ( isset( $expressions[$record['id']] ) )  {
            if ( !is_array($expressions[$record['id']]) ) $expressions[$record['id']] = array();
            $expressions[$record['id']] = array_merge( $expressions[$record['id']], $record );
          }
          $record = null;
        }
        // debut d’un enregistrement qui nous intéresse
        else if ( preg_match(  '@<http://data.bnf.fr/ark:/12148/([^#>]+)#frbr:Expression> a frbr:Expression@', $line, $matches ) ) {
          $record = array();
          $record['id'] = $matches[1];
          $key = "";
          $value = "";
        }
        // pas encore d’enregistrement
        else if ( !$record );
        // début d’une propriété
        else if ( preg_match( '@[a-z\-]+:([a-zA-Z\-]+) (.+)@', $line, $match_kv ) ) {
          // enregistrement de la propriété précédente
          if ( $key && $value ) {
            if ( "language" == $key ) {
              preg_match( "@<http://id.loc.gov/vocabulary/iso639-2/([^>]+)>@", $value, $match_lang );
              if ( "fre" == $match_lang[1] ) $match_lang[1] = "fr";
              $record['language'] = $match_lang[1];
            }
            else if ( "type" == $key ) {
              preg_match( "@dcmitype:([^ ]+)@", $value, $match_type );
              $record['type'] = $match_type[1];
            }
            else if ( "subject" == $key ) {
              preg_match_all( "@<http://data.bnf.fr/ark:/12148/([^>]+)>@", $value, $match_subject );
              $record['subject'] = $match_subject[1];
            }
          }
          $key = $match_kv[1];
          $value = $match_kv[2];
        }
        // ajouter à la valeur courante
        else {
          $value .= $line;
        }
      }
    }
    return $expressions;
  }

 /**
  * Traitement de
  * databnf_person_authors__contributions_*.n3
  *
  * Avec un identifiant de personne
  * collecter ses contributions
  * renvoyer les identifiants d’“expression”
  *
  * Avec des identifiants d’expressions
  * Repasser les contributions pour retrouver les autres contributeurs
  * Renvoyer un tableau
  * 0:Source=author 1:Target="expression" 2:Type=role
  */
  function contributions( $id=null, $biblio=null )
  {
    if ( $biblio ) $biblio = array_flip( $biblio );
    $ret = array();
    $dir = dirname(__FILE__).'/databnf_person_authors_n3/';
    foreach( glob( $dir.'databnf_person_authors__contributions_*.n3' ) as $filepath ) {
      fwrite(STDERR, basename($filepath)."\n");
      $filestream = fopen( $filepath, "r" );
      $record = array();
      while ( ( $line = fgets( $filestream ) ) !== false) {
        // ligne blanche, traitement
        if ( !trim( $line ) ) {
          if ( !$record );
          else if ( $id && $record['author'] == $id ) {
            $ret[] = $record['expression'];
          }
          else if ( $biblio && isset( $biblio[$record['expression']] ) ) {
            $ret[] = array( $record['author'], $record['expression'], $record['bnfrole'] );
          }
          $record = array();
          continue;
        }
        $re = "@<http://data.bnf.fr/ark:/12148/([^#]+)#Expression> bnfroles:r([0-9]+) <http://data.bnf.fr/ark:/12148/([^#]+)#foaf:Person>@";
        if ( preg_match( $re, $line, $matches ) ) {
          $record['expression'] = $matches[1];
          $record['author'] = $matches[3];
          $record['bnfrole'] = $matches[2];
        }
      }
      fclose( $filestream );
    }
    return $ret;
  }

  /**
   * Récupérer tout ce qui concerne un auteur
   */
  function scan( $id )
  {
    $folders = array(
      "databnf_person_authors_n3",
      "databnf_editions_n3",
      "databnf_works_n3",
      "databnf_study_n3",
    );
    foreach ( $folders as $dirpath ) {
      $dirstream = opendir( $dirpath );
      echo "=== ".$dirpath."\n";
      while ( false !== ( $filepath = readdir($dirstream) ) ) {
        if ( '.' === $filepath ) continue;
        if ( '..' === $filepath ) continue;
        $filestream = fopen( $dirpath.'/'.$filepath, "r" );
        fwrite(STDERR, "$filepath\n");
        $record = array();
        $keep = false;
        while ( ( $line = fgets( $filestream ) ) !== false) {
          // ligne blanche, sortir l’enregistement ?
          if ( !trim( $line ) ) {
            if ( $keep ) {
              echo "\n=== ".$filepath."\n";
              echo implode( "", $record );
            }
            $record = array();
            $keep = false;
          }
          if ( strpos( $line, $id) ) $keep = true;
          $record[] = $line;
        }
        fclose( $filestream );
      }
      closedir( $dirstream );
    }
  }
}



?>
