PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;
-- ne pas vérifier l’intégrité au chargement
PRAGMA foreign_keys = OFF;

CREATE TABLE document (
  -- document
  ark         TEXT NOT NULL, -- cote BNF
  type        TEXT, -- pour l’instant Text|Sound|MovingImage|StillImage|Image|Archive|Score|Map|Microfilm
  lang        TEXT, -- langue principale
  title       TEXT, -- titre du document
  dateline    TEXT, -- datation selon la notice
  date        INTEGER, -- année de publication
  imprint     TEXT, -- adresse éditoriale
  place       TEXT, -- lieu de publication
  publisher   TEXT, -- éditeur extrait de l’adresse éditoriale
  description TEXT, -- description dans la notice
  pages       INTEGER, -- nombre de pages (quand pertinent)
  size        INTEGER, -- in- : 8, 4, 12… peu fiable
  gallica     TEXT, -- lien à une numérisation Gallica

  book        BOOLEAN, -- texte de 45 pages et + (inclus théâtre et BD)
  paris       BOOLEAN, -- publié à paris (redondance utile aux perfs)
  hasgall     BOOLEAN, -- redondance, sur champ gallica
  pers        BOOLEAN, -- auteur principal personne, redondant avec la jointure mais utile aux perfs
  birthyear   INTEGER, -- année de naissance de l’auteur principal, pour req antiquité ou sècles
  birthdec    INTEGER, -- décennie de naissance de l’auteur principal, pour générations
  deathyear   INTEGER, -- date de mort de l’auteur principal
  age         INTEGER, -- âge de l’auteur principal à la publication si vivant
  agedec      INTEGER, -- décade de l’auteur principal à la publication si vivant
  posthum     BOOLEAN, -- auteur principal, à la date d’édition, 1=mort, 0=vivant, null=?
  gender      INTEGER, -- sexe de l’auteur principal

  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
-- Index, fondamentaux pour sortir rapidement les courbes
CREATE UNIQUE INDEX document_ark ON document(ark);
CREATE INDEX document_book ON document(book, lang, date);
-- WHERE (date >= 1890 AND date <= 1895) AND type = 'Text' AND lang = 'grc'
CREATE INDEX document_type ON document(type, lang, date, pages);
CREATE INDEX document_date ON document(date, lang, type);
-- WHERE lang = 'fre' AND book = 1 AND date > 1890 AND date < 1896;
CREATE INDEX document_date2 ON document(lang, book, date, age);
-- SELECT avg(age) FROM document WHERE lang = 'fre' AND book = 1 AND gender=1 AND date >= 1890 AND date <= 1895
CREATE INDEX document_date3 ON document(lang, book, gender, date, age);

CREATE INDEX document_place ON document(place, type, lang, date, pages);
CREATE INDEX document_paris ON document(paris, type, lang, date, pages);
CREATE INDEX document_paris2 ON document(paris, type, date, pages);
CREATE INDEX document_pages ON document(type, lang, date, pages);
CREATE INDEX document_pages2 ON document(date, lang, pages);
CREATE INDEX document_pages3 ON document(date, type, pages);
-- siècles WHERE date = 2014 AND book = 1 AND lang = 'fre' AND posthum=1 AND birthyear >= 1880;
CREATE INDEX document_birthyear ON document(book, lang, posthum, date, birthyear);
CREATE INDEX document_birthyear3 ON document(book, posthum, date, birthyear);
--  WHERE  type = 'Text' AND (lang = 'frm' OR lang = 'fre') AND birthyear < 1400 AND date >= 1890 AND date <= 1895;
CREATE INDEX document_birthyear2 ON document(type, lang, birthyear ASC, date ASC);
CREATE INDEX document_pers ON document(pers, type, date, lang);
-- SET book = 1 WHERE type = 'Text' AND pages >= 45
CREATE INDEX document_type2 ON document(type, pages);
-- WHERE posthum=1  AND lang = 'fre' AND book = 1 AND date >= ? AND date <= ?" (nouveautés)
CREATE INDEX document_posthum ON document(posthum, lang, book, date);
-- WHERE lang='fre' AND book=1 AND posthum = 0 AND gender = 2 AND date >= 1920 AND date <= 1930;
CREATE INDEX document_posthum2 ON document(posthum, lang, gender, book, date);
-- generations.php SELECT gender, COUNT(*) AS count FROM document WHERE book = 1 AND lang = 'fre' AND posthum=0 AND date = 2015 GROUP BY gender
CREATE INDEX document_posthum3 ON document(posthum, book, lang, date, gender);
-- tri par âge pour repérer les erreurs
CREATE INDEX document_age ON document(age);
-- generations.php SELECT DISTINCT birthdec FROM document WHERE book=1 AND lang = 'fre' AND date >= 1800 AND date <= 1899
CREATE INDEX document_birthdec ON document(book, lang, posthum, date, birthdec);
-- generations.php SELECT DISTINCT birthdec FROM document WHERE book=1 AND lang = 'fre' AND gender=2 AND date >= 1800 AND date <= 1899
CREATE INDEX document_birthdec2 ON document(book, lang, gender, posthum, date, birthdec);
-- ages.php SELECT DISTINCT agedec FROM document WHERE book=1 AND lang = 'fre' AND date >= 1800 AND date <= 1899
CREATE INDEX document_agedec ON document(book, lang, posthum, date, agedec);
-- ages.php SELECT DISTINCT agedec FROM document WHERE book=1 AND lang = 'fre' AND gender=2 AND date >= 1800 AND date <= 1899
CREATE INDEX document_agedec2 ON document(book, lang, posthum, gender, date, agedec);


CREATE TABLE person (
  -- Autorité personne
  ark         TEXT NOT NULL, -- cote BNF
  name        TEXT NOT NULL, -- nom affichable
  family      TEXT NOT NULL, -- nom de famille
  given       TEXT, -- prénom
  sort        TEXT NOT NULL, -- version ASCII bas de casse du nom pour tris
  ogender     TEXT, -- sexe, pris de la notice lorsqu’indiqué
  gender      INTEGER, -- sexe, indiqué ou inféré du prénom

  birth       TEXT, -- date de naissance comme indiquée sur la notice
  death       TEXT, -- date de naissance comme indiques sur la notice
  birthyear   INTEGER, -- année de naissance exacte lorsque possible
  deathyear   INTEGER, -- année de mort exacte lorsque possible
  birthplace  TEXT, -- lieu de naissance lorsqu’indiqué
  deathplace  TEXT, -- lieu de mort lorsqu’indiqué
  age         INTEGER, -- âge à la mort

  lang        TEXT, -- langue principale, pas très fiable
  country     TEXT, -- pays d’exercice, pas très fiable
  note        TEXT, -- un text de note

  fr          BOOLEAN, -- auteur français ou francophone ayant signé au moins un document
  birthparis  BOOLEAN, -- auteur français né à Paris
  deathparis  BOOLEAN, -- auteur français mort à Paris
  writes      BOOLEAN, -- ???
  docs        INTEGER, -- nombre de documents dont la personne est auteur principal
  doc1        INTEGER, -- date du premier document
  books       INTEGER, -- nombre de documents de plus de 50 p. dont la personne est auteur principal, anthumes et posthumes
  opus1       INTEGER, -- date du premier livre (document > 50 p.)
  age1        INTEGER, -- âge au premier livre
  agedec      INTEGER, -- âge à la mort, décade
  posthum     INTEGER, -- nombre de "docs" attribués après la mort
  anthum      INTEGER, -- nombre de "docs" attribués avant la mort

  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX person_ark ON person(ark);
CREATE INDEX person_sort ON person(sort, posthum);
-- SELECT count(*) AS count FROM person WHERE fr = 1 AND gender = 1 AND birthyear >= ? AND birthyear <= ? ;
CREATE INDEX person_birthyear ON person(fr, gender, birthyear);
-- natalite.php SELECT count(*) AS count FROM person WHERE fr = 1 AND gender = 1 AND books >= 5  AND birthyear >= 1880 AND birthyear <= 1910;
CREATE INDEX person_birthyear2 ON person(fr, gender, books, birthyear);
-- SELECT avg(age) FROM person WHERE fr = 1 AND gender = 1 AND deathyear >= ? AND deathyear <= ?
CREATE INDEX person_deathyear ON person(fr, gender, deathyear, age);
-- mortalite.php SELECT gender, count(*), avg(age) FROM person WHERE fr = 1 AND deathyear >= 1900 AND deathyear <= 2015 AND books > 10 GROUP BY gender ORDER BY gender;
CREATE INDEX person_deathyear2 ON person(fr, deathyear, books, gender, age);
CREATE INDEX person_posthum ON person(posthum, birthyear);
CREATE INDEX person_anthum ON person(anthum, birthyear);
CREATE INDEX person_docs ON person(docs, birthyear);
CREATE INDEX person_gender ON person(gender, writes, lang, birthyear);
CREATE INDEX person_writes ON person(country, writes, gender, birthyear, deathyear);
-- population.php SELECT gender, count(*) AS count FROM person WHERE fr = 1 AND doc1 <= 2010 AND (deathyear >= 2010 OR deathyear IS NULL) AND books > 10 GROUP BY gender ORDER BY gender
CREATE INDEX person_fr ON person(fr, books, deathyear, doc1, gender);
-- population.php SELECT gender, count(*) AS count FROM person WHERE fr = 1 AND (deathyear >= 2010 OR deathyear IS NULL) AND doc1 <= 2010  GROUP BY gender ORDER BY gender;
CREATE INDEX person_doc1 ON person(fr, doc1, deathyear, gender);
-- SELECT deathparis, count(*) AS count, avg(age) AS age FROM person WHERE fr = 1 AND gender = 2 AND deathyear >= 1890 AND deathyear <= 1900 GROUP BY deathparis;
CREATE INDEX person_deathparis ON person(fr, gender, deathyear, deathparis, age);
-- SELECT count(*) AS count, birthparis FROM person WHERE fr = 1 AND gender = 1 AND opus1 >= ? AND opus1 <= ? GROUP BY birthparis ORDER BY birthparis
CREATE INDEX person_birthparis ON person(fr, gender, opus1, birthparis, books);
-- SELECT avg(age1) FROM person WHERE fr = 1 AND gender=1 AND opus1 >= 1800 AND opus1 <= 1810 ;
CREATE INDEX person_opus1 ON person(fr, gender, opus1, age1);
-- SELECT avg(age1) FROM person WHERE fr = 1 AND opus1 >= 1800 AND opus1 <= 1810 ;
CREATE INDEX person_opus2 ON person(fr, opus1, age1);
-- tri par âge pour repérer les erreurs
CREATE INDEX person_age ON person(age);

CREATE TABLE contribution (
  -- lien d’une personne à un document
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid
  person       INTEGER REFERENCES person(id), -- lien à une œuvre, par son rowid
  role         INTEGER, -- nature de la responsabilité, code BNF http://data.bnf.fr/vocabulary/roles/

  type         INTEGER, -- role simplifié. 10: auteur, 11:editeur, 12:traducteur, 20:musique, 30:illustration, 40:spectacle

  date         INTEGER, -- redondant avec la date de document, mais nécessaire
  posthum      BOOLEAN, -- document publié après la mort de l'auteur
  writes       BOOLEAN, -- précalcul sur le code de rôle
  book         BOOLEAN, -- redondance, document livre (>= 45 p.)
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
-- pour person.* WHERE person=person.id AND writes = 1 AND book = 1 AND posthum = 0  ORDER BY date LIMIT 1
CREATE INDEX contribution_person ON contribution(person, writes, book, posthum, date);
CREATE INDEX contribution_document ON contribution(document, writes, posthum, date);
-- premier auteur d’un document
CREATE INDEX contribution_document2 ON contribution(document, date, writes);
-- pour indexation type de role
CREATE INDEX contribution_role ON contribution(role);
-- pour vérifier les partitions
CREATE INDEX contribution_type ON contribution(document, type);

CREATE TABLE subject (
  -- lien entre un document et des sujets
  document  INTEGER REFERENCES document(id), -- lien au document par son rowid
  rameau    INTEGER, --
  url       STRING,  --
  id        INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX subject_id ON subject(document, rameau);


CREATE TABLE org (
  -- Autorité organisation
  ark         TEXT NOT NULL, -- cote BNF
  name        TEXT NOT NULL, -- nom affichable
  sort        TEXT NOT NULL, -- version ASCII bas de casse du nom pour tri
  start       INTEGER, -- date de début
  end         TEXT, -- date de fin
  note        TEXT, -- un texte de note
  fr          BOOLEAN, -- auteur français ou francophone ayant signé au moins un document en français
  docs        INTEGER, -- nombre de documents dont la personne est auteur principal
  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);

CREATE TABLE work (
  -- Œuvre, pas très fiable
  ark           TEXT NOT NULL, -- cote BNF
  title         TEXT NOT NULL, -- titre
  date          INTEGER,  -- date
  lang          TEXT,  -- langue (?)
  type          TEXT, -- text, sound, image, video
  dewey         INTEGER, -- classification, à tester
  country       TEXT,
  versions      INTEGER, -- nombre de versions
  id            INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX work_ark ON work(ark);
CREATE INDEX work_date ON work(date);
CREATE INDEX work_versions ON work(versions);

CREATE TABLE version (
  -- lien entre un document et une œuvre sujet, pas très fiable
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid (fixé par lot après chargement)
  work         INTEGER REFERENCES work(id), -- lien à l’œuvre, par son rowid (fixé par lot après chargement)
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE INDEX version_work ON version(work, document);
CREATE INDEX version_document ON version(document, work);

CREATE TABLE dewey (
  label        STRING,  --
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);

CREATE TABLE persdewey (
  person       INTEGER REFERENCES person(id),  --
  dewey        INTEGER REFERENCES dewey(id), --
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX persdewey_person ON persdewey(person, dewey);
CREATE UNIQUE INDEX persdewey_dewey ON persdewey(dewey, person);

CREATE VIRTUAL TABLE title USING FTS3 (
  -- recherche dans les mots du titres
  text        TEXT  -- exact text
);

CREATE TABLE creation (
  -- lien entre une œuvre et ses auteurs
  person       INTEGER REFERENCES person(id), -- lien à une personne, par son rowid
  work         INTEGER REFERENCES work(id), -- lien à l’œuvre, par son rowid
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX creation_work ON creation(work, person);
CREATE UNIQUE INDEX creation_person ON creation(person, work);

CREATE TABLE study (
  -- lien d’un document vers une entité
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid
  entity       INTEGER, -- lien à une personne, par son rowid
  type         INTEGER,
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX study_document ON study(document, entity);
CREATE UNIQUE INDEX study_entity ON study(entity, document);


CREATE TABLE name (
  -- TODO, Noms de personnes, formes canonique et alternatives
  person      INTEGER REFERENCES person(id),
  label       TEXT,
  sort        TEXT,
  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);

CREATE TABLE year (
  -- Table temporaire pour moyennes annuelles
  val1 INTEGER,
  val2 INTEGER,
  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
-- Remplir la table avec des années
WITH RECURSIVE
  cnt(x) AS (
     SELECT 1500
     UNION ALL
     SELECT x+1 FROM cnt
      LIMIT 516
 )
INSERT INTO year(id) SELECT x FROM cnt;


CREATE TRIGGER IF NOT EXISTS personDel
-- Pour supprimer un auteur comme Louis XIV
  BEFORE DELETE ON person
  FOR EACH ROW BEGIN
    UPDATE document SET
      pers = NULL,
      birthyear = NULL,
      birthdec = NULL,
      deathyear = NULL,
      age = NULL,
      posthum = NULL,
      gender = NULL
    WHERE id IN (SELECT document FROM contribution WHERE person = OLD.id AND writes=1);
    DELETE FROM contribution WHERE person=OLD.id;
END;
-- DELETE FROM person WHERE id IN (12008165, 12329432, 12106345) ; -- Louis XVI, cb123294328 Boutillier du Retail
