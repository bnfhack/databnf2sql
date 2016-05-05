<?php

/*
Questions pour mieux comprendre les données
 * Combien de manifestations pour une expression ?
 *

*/

mb_internal_encoding ("UTF-8");
// GraphBNF::contributions();
// cb11888978p, Apollinaire
// GraphBNF::gephi( "cb11888978p" );
GraphBNF::scan( "cb119347789" );




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
   * Réseau autour d’un auteur
   * Apollinaire="cb11888978p"
   *
   */
  static function gephi( $author, $pre="_" )
  {
    $expressions = GraphBNF::contributions( $author );
    // expressions uniques, vérifiées
    $relations = GraphBNF::contributions( null, $expressions );
    // filtrer les relation par date ?
    $writer = fopen( $pre.'edges.tsv', "w" );
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

    $writer = fopen( $pre.'nodes.tsv', "w" );
    fwrite( $writer, "Id\tLabel\ttype\tdate\n");
    // d’abord écrire les œuvres (par dessousles auteurs)
    $manifestations = GraphBNF::manifestations( $expressions );
    print_r( $manifestations );
    foreach( $manifestations as $identifier => $row ) {
      $title = $row['title'];
      if ( mb_strlen( $title ) > 50 ) $title = mb_substr( $title, 0, mb_strpos( $title, ' ', 40 ))+' […]';
      fwrite( $writer, $identifier );
      fwrite( $writer, "\t" );
      fwrite( $writer, $title );
      fwrite( $writer, "\t" );
      // todo archive
      fwrite( $writer, $row['type'] );
      fwrite( $writer, "\t" );
      if ( isset( $row['date'] ) ) fwrite( $writer, $row['date'] );
      fwrite( $writer, "\n" );
    }
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
      fwrite( $writer, "\t" );
      fwrite( $writer, $row['byear'] );
      fwrite( $writer, "\n" );
    }
    fclose( $writer );
  }

  /**
   *
   */
  static function works( $id )
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
  static function manifestations( $expressions )
  {
    $expressions = array_flip( $expressions );
    // collecter les manifestations
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
  static function contributions( $id=null, $biblio=null )
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
  static function scan( $id )
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
