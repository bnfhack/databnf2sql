PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;  -- blob optimisation https://www.sqlite.org/intern-v-extern-blob.html
PRAGMA foreign_keys = ON;
-- The VACUUM command may change the ROWIDs of entries in any tables that do not have an explicit INTEGER PRIMARY KEY

CREATE TABLE person (
  -- Autorité personne
  id          INTEGER, -- rowid auto
  code        TEXT NOT NULL, -- code BNF
  name        TEXT NOT NULL, -- nom affichable
  family      TEXT NOT NULL, -- nom de famille
  given       TEXT, -- prénom
  gender      INTEGER, -- inférence d’un sexe sur le prénom

  birth       TEXT, -- date de naissance comme indiquée sur la notice
  death       TEXT, -- date de naissance comme indiques sur la notice
  byear       INTEGER, -- année de naissance exacte lorsque possible
  dyear       INTEGER, -- année de mort exacte lorsque possible
  age         INTEGER, -- âge à la mort
  birthplace  TEXT, -- lieu de naissance lorsqu’indiqué
  deathplace  TEXT, -- lieu de mort lorsqu’indiqué

  lang        TEXT, -- langue principale
  country     TEXT, -- pays d’exercice
  dewey       INTEGER, -- code sujet (pas fiable)
  note        TEXT, -- une note
  PRIMARY KEY(id ASC)
);

CREATE UNIQUE INDEX person_code ON person( code );
CREATE INDEX person_family ON person( family );
CREATE INDEX person_given ON person( given );
CREATE INDEX person_gender ON person( gender );
CREATE INDEX person_birth ON person( birth );
CREATE INDEX person_death ON person( death );
CREATE INDEX person_age ON person( age );
CREATE INDEX person_byear ON person( byear );
CREATE INDEX person_dyear ON person( dyear );
CREATE INDEX person_birthplace ON person( birthplace );
CREATE INDEX person_deathplace ON person( deathplace );
CREATE INDEX person_lang ON person( lang );
CREATE INDEX person_country ON person( country );
CREATE INDEX person_dewey ON person( dewey );

CREATE TABLE document (
  -- document
  id          INTEGER, -- rowid auto
  code        TEXT NOT NULL, -- cote BNF
  title       TEXT, -- titre du document
  date        INTEGER, -- année de publication
  place       TEXT, -- lieu de publication
  lang        TEXT, -- langue principale
  type        TEXT, -- pour l’instant Text|Sound|MovingImage|StillImage|Archive|Partition
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX document_code ON document( code );
CREATE INDEX document_type ON document( type );
CREATE INDEX document_date ON document( date );
CREATE INDEX document_lang ON document( lang );
CREATE INDEX document_place ON document( place );

CREATE TABLE contribution (
  -- lien d’une personne auteur à un document
  id           INTEGER, -- rowid auto
  documentC    TEXT NOT NULL, -- lien au document par la cote
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid (fixé par lot après chargement)
  role         INTEGER, -- nature de la responsabilité
  personC      TEXT NOT NULL, -- lien à une personne auteur, par la cote
  person       INTEGER REFERENCES work(id), -- lien à une œuvre, par son rowid (fixé par lot après chargement)
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX contribution_personC ON contribution( personC, documentC );
CREATE UNIQUE INDEX contribution_documentC ON contribution( documentC, personC );
CREATE UNIQUE INDEX contribution_document ON contribution( document, person );
CREATE UNIQUE INDEX contribution_person ON contribution( person, document );
CREATE INDEX contribution_role ON contribution( person, role );

CREATE TABLE work (
  -- Œuvre
  id            INTEGER, -- rowid auto
  code          TEXT NOT NULL, -- cote BNF
  title         TEXT NOT NULL, -- titre
  date          TEXT,  -- date
  lang          TEXT,  -- langue (?)
  type          TEXT NOT NULL, -- text, sound, image, video
  dewey         INTEGER, -- classification, à tester
  country       TEXT,
  PRIMARY KEY(id ASC)
);

CREATE TABLE version (
  -- lien entre un document et une œuvre sujet, permet de suivre les rééditions
  id           INTEGER, -- rowid auto
  documentC    TEXT NOT NULL, -- lien au document par la cote
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid (fixé par lot après chargement)
  workC        TEXT NOT NULL, -- lien à l’œuvre, par la cote
  work         INTEGER REFERENCES work(id), -- lien à l’œuvre, par son rowid (fixé par lot après chargement)
  PRIMARY KEY(id ASC)
);
CREATE INDEX version_document ON version( document );
CREATE INDEX version_documentC ON version( documentC );
CREATE INDEX version_work ON version( work );
CREATE INDEX version_workC ON version( workC );

CREATE TABLE date (
  -- un compteur pour facilement produire des requêtes par date
  year INTEGER NOT NULL
);
CREATE UNIQUE INDEX date_year ON date( year );
-- TODO alimenter les années ?
