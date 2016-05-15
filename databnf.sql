PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;  -- blob optimisation https://www.sqlite.org/intern-v-extern-blob.html
PRAGMA foreign_keys = ON;
-- The VACUUM command may change the ROWIDs of entries in any tables that do not have an explicit INTEGER PRIMARY KEY


CREATE TABLE person (
  -- Autorité personne
  code        TEXT NOT NULL, -- code BNF
  sort        TEXT NOT NULL, -- version ASCII bas de casse du nom
  name        TEXT NOT NULL, -- nom affichable
  family      TEXT NOT NULL, -- nom de famille
  given       TEXT, -- prénom
  gender      INTEGER, -- inférence d’un sexe sur le prénom

  birth       TEXT, -- date de naissance comme indiquée sur la notice
  death       TEXT, -- date de naissance comme indiques sur la notice
  birthyear   INTEGER, -- année de naissance exacte lorsque possible
  deathyear   INTEGER, -- année de mort exacte lorsque possible
  birthplace  TEXT, -- lieu de naissance lorsqu’indiqué
  deathplace  TEXT, -- lieu de mort lorsqu’indiqué
  age         INTEGER, -- âge à la mort

  lang        TEXT, -- langue principale
  country     TEXT, -- pays d’exercice
  docs        INTEGER, -- nombre de documents dont la personne est auteur
  note        TEXT, -- un text de note
  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);

CREATE INDEX person_docs ON person( docs DESC);
CREATE UNIQUE INDEX person_code ON person( code );
CREATE INDEX person_sort ON person( sort );
CREATE INDEX person_family ON person( family, given );
CREATE INDEX person_given ON person( given );
CREATE INDEX person_gender ON person( gender );
CREATE INDEX person_birth ON person( birth );
CREATE INDEX person_death ON person( death );
CREATE INDEX person_age ON person( age );
CREATE INDEX person_birthyear ON person( birthyear );
CREATE INDEX person_deathyear ON person( deathyear );
CREATE INDEX person_birthplace ON person( birthplace );
CREATE INDEX person_deathplace ON person( deathplace );
CREATE INDEX person_lang ON person( lang );
CREATE INDEX person_country ON person( country );

CREATE TABLE document (
  -- document
  code        TEXT NOT NULL, -- cote BNF
  title       TEXT, -- titre du document
  date        INTEGER, -- année de publication
  place       TEXT, -- lieu de publication
  lang        TEXT, -- langue principale
  type        TEXT, -- pour l’instant Text|Sound|MovingImage|StillImage|Archive|Score
  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX document_code ON document( code );
CREATE INDEX document_type ON document( type, lang, date );
CREATE INDEX document_date ON document( date, lang, type );
CREATE INDEX document_lang ON document( lang, date, type );
CREATE INDEX document_place ON document( place );

CREATE TABLE work (
  -- Œuvre
  code          TEXT NOT NULL, -- cote BNF
  title         TEXT NOT NULL, -- titre
  date          TEXT,  -- date
  lang          TEXT,  -- langue (?)
  versions      INTEGER, -- nombre de versions
  type          TEXT, -- text, sound, image, video
  dewey         INTEGER, -- classification, à tester
  country       TEXT,
  id            INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX work_code ON work( code );
CREATE INDEX work_versions ON work( versions );
CREATE INDEX work_date ON work( date );

CREATE TABLE contribution (
  -- lien d’une personne auteur à un document
  role         INTEGER, -- nature de la responsabilité
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid (fixé par lot après chargement)
  person       INTEGER REFERENCES person(id), -- lien à une œuvre, par son rowid (fixé par lot après chargement)
  documentC    TEXT NOT NULL, -- lien au document par la cote
  personC      TEXT NOT NULL, -- lien à une personne auteur, par la cote
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX contribution_personC ON contribution( personC, documentC );
CREATE UNIQUE INDEX contribution_documentC ON contribution( documentC, personC );
CREATE UNIQUE INDEX contribution_document ON contribution( document, person );
CREATE UNIQUE INDEX contribution_person ON contribution( person, document );
CREATE INDEX contribution_role ON contribution( person, role );

CREATE TABLE version (
  -- lien entre un document et une œuvre sujet, permet de suivre les rééditions
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid (fixé par lot après chargement)
  work         INTEGER REFERENCES work(id), -- lien à l’œuvre, par son rowid (fixé par lot après chargement)
  documentC    TEXT NOT NULL, -- lien au document par la cote
  workC        TEXT NOT NULL, -- lien à l’œuvre, par la cote
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
-- UNIQUE ne marche pas encore
CREATE INDEX version_workC ON version( workC, documentC );
CREATE INDEX version_documentC ON version( documentC, workC );
CREATE INDEX version_work ON version( work, document );
CREATE INDEX version_document ON version( document, work );

CREATE TABLE creation (
  -- lien entre une œuvre et ses auteurs
  person       INTEGER REFERENCES person(id), -- lien à une personne, par son rowid
  work         INTEGER REFERENCES work(id), -- lien à l’œuvre, par son rowid
  personC      TEXT NOT NULL, -- lien à une personne, par la cote
  workC        TEXT NOT NULL, -- lien à l’œuvre, par la cote
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX creation_work ON creation( work, person );
CREATE UNIQUE INDEX creation_person ON creation( person, work );
CREATE UNIQUE INDEX creation_personC ON creation( personC, workC );
CREATE UNIQUE INDEX creation_workC ON creation( workC, personC );

CREATE TABLE name (
  -- TODO, Noms de personnes, formes canonique et alternatives
  person      INTEGER REFERENCES person(id),
  label       TEXT,
  sort        TEXT,
  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
