PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;
PRAGMA foreign_keys = ON;

CREATE TABLE document (
  -- document
  ark         TEXT NOT NULL, -- cote BNF
  title       TEXT, -- titre du document
  date        INTEGER, -- année de publication
  place       TEXT, -- lieu de publication
  publisher   TEXT, -- éditeur extrait de l’adresse éditoriale
  imprint     TEXT, -- adresse éditoriale
  lang        TEXT, -- langue principale
  type        TEXT, -- pour l’instant Text|Sound|MovingImage|StillImage|Archive|Score
  pages       INTEGER, -- nombre de pages (quand pertinent)
  size        INTEGER, -- in- : 8, 4, 12… peu fiable
  description TEXT, -- description dans la notice
  gallica     TEXT, -- lien à une numérisation Gallica

  book        BOOLEAN, -- texte de 45 pages et + (inclus théâtre et BD)
  paris       BOOLEAN, -- publié à paris (redondance utile aux perfs)
  hasgall     BOOLEAN, -- redondance, sur champ gallica
  pers        BOOLEAN, -- auteur principal personne, redondant avec la jointure mais utile aux perfs
  birthyear   INTEGER, -- date de naissance de l’auteur principal, pour req antiquité ou sècles
  deathyear   INTEGER, -- date de mort de l’auteur principal, redondance
  posthum     BOOLEAN, -- si l’auteur principal est mort à la date d’édition
  gender      INTEGER, -- sexe de l’auteur principal

  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
-- Index, fondamentaux pour sortir rapidement les courbes
CREATE UNIQUE INDEX document_ark ON document( ark );
CREATE INDEX document_book ON document( book, lang, date );
CREATE INDEX document_type ON document( type, lang, date, pages );
CREATE INDEX document_date ON document( date, lang, type );
CREATE INDEX document_place ON document( place, type, lang, date, pages );
CREATE INDEX document_paris ON document( paris, type, lang, date, pages);
CREATE INDEX document_paris2 ON document( paris, type, date, pages);
CREATE INDEX document_pages ON document( pages, lang, date );
CREATE INDEX document_pages2 ON document( date, lang, pages  );
CREATE INDEX document_pages3 ON document( date, type, pages  );
-- pour le graphe de répartition des siècles
CREATE INDEX document_birthyear ON document( date, type, posthum, birthyear );
-- pour le graphe latin et antiquité
CREATE INDEX document_birthyear2 ON document( date, type, birthyear, lang );
CREATE INDEX document_pers ON document( pers, type, date, lang );
-- pour calculer plus vite le champ book
CREATE INDEX document_type2 ON document( type, pages );
-- WHERE lang = 'fre' AND book = 1 AND posthum=0 AND gender=2 AND date >= ? AND date <= ?"
CREATE INDEX document_posthum ON document( lang, book, posthum, gender, date );
-- WHERE lang = 'fre' AND  book = 1 AND posthum = 1 AND  date = ?
CREATE INDEX document_posthum2 ON document( posthum, book, lang, date );

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
  writes      BOOLEAN, -- cache, docs>0
  docs        INTEGER, -- cache, nombre de documents dont la personne est auteur principal
  posthum     INTEGER, -- cache, nombre de "docs" attribués après la mort
  anthum      INTEGER, -- cache, nombre de "docs" attribués avant la mort
  opus1       INTEGER, -- date du premier document

  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);

CREATE UNIQUE INDEX person_ark ON person( ark );
CREATE INDEX person_sort ON person( sort, posthum );
CREATE INDEX person_birthyear ON person( fr, birthyear, gender, birthparis );
CREATE INDEX person_birthyear2 ON person( birthyear, deathyear );
-- SELECT avg(age) FROM person WHERE fr = 1 AND deathyear >= ? AND deathyear <= ? AND gender = 1
CREATE INDEX person_deathyear ON person( fr, gender, deathyear, age );
CREATE INDEX person_posthum ON person( posthum, birthyear );
CREATE INDEX person_anthum ON person( anthum, birthyear );
CREATE INDEX person_docs ON person( docs, birthyear );
CREATE INDEX person_gender ON person( gender, writes, lang, birthyear );
CREATE INDEX person_writes ON person( country, writes, gender, birthyear, deathyear );
-- pour la population des auteurs vivants
CREATE INDEX person_fr ON person( fr, gender, opus1, deathyear, books );
CREATE INDEX person_deathparis ON person( deathparis, deathyear, fr, gender );
CREATE INDEX person_opus1 ON person( fr, opus1);


CREATE TABLE contribution (
  -- lien d’une personne à un document
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid
  person       INTEGER REFERENCES person(id), -- lien à une œuvre, par son rowid
  role         INTEGER, -- nature de la responsabilité

  date         INTEGER, -- redondant avec la date de document, mais nécessaire
  posthum      BOOLEAN, -- document publié après la mort de l'auteur
  writes       BOOLEAN, -- précalcul sur le code de rôle
  book         BOOLEAN, -- redondance, document livre (>= 45 p.)
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
-- pour indexation person.docs, person.posthum, person.anthum
CREATE INDEX contribution_person ON contribution( person, writes, book, posthum, date );
CREATE INDEX contribution_document ON contribution( document, writes, posthum, date );
-- premier auteur d’un document
CREATE INDEX contribution_document2 ON contribution( document, id );


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
CREATE UNIQUE INDEX work_ark ON work( ark );
CREATE INDEX work_date ON work( date );
CREATE INDEX work_versions ON work( versions );

CREATE TABLE version (
  -- lien entre un document et une œuvre sujet, pas très fiable
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid (fixé par lot après chargement)
  work         INTEGER REFERENCES work(id), -- lien à l’œuvre, par son rowid (fixé par lot après chargement)
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE INDEX version_work ON version( work, document );
CREATE INDEX version_document ON version( document, work );

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
CREATE UNIQUE INDEX creation_work ON creation( work, person );
CREATE UNIQUE INDEX creation_person ON creation( person, work );

CREATE TABLE studyp (
  -- lien d’un document vers un auteur personne (person)
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid
  person       INTEGER REFERENCES person(id), -- lien à une personne, par son rowid
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX studyp_document ON studyp( document, person );
CREATE UNIQUE INDEX studyp_person ON studyp( person, document );
CREATE TABLE studyw (
  -- lien d’un document vers un titre normalisé (work)
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid
  work         INTEGER REFERENCES work(id), -- lien à l’œuvre, par son rowid
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX studyw_document ON studyw( document, work );
CREATE UNIQUE INDEX studyw_work ON studyw( work, document );


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
INSERT INTO year( id ) SELECT x FROM cnt;
