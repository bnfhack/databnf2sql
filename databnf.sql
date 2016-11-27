PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;  -- blob optimisation https://www.sqlite.org/intern-v-extern-blob.html
PRAGMA foreign_keys = ON;
-- The VACUUM command may change the ROWIDs of entries in any tables that do not have an explicit INTEGER PRIMARY KEY

CREATE TABLE document (
  -- document
  ark         TEXT NOT NULL, -- cote BNF
  title       TEXT, -- titre du document
  date        INTEGER, -- année de publication
  place       TEXT, -- lieu de publication
  publisher   TEXT, -- éditeur extrait de l’adresse éditoriale
  imprint     TEXT, -- adresse éditoriale
  paris       BOOLEAN, -- publié à paris (utile aux perfs)
  lang        TEXT, -- langue principale
  type        TEXT, -- pour l’instant Text|Sound|MovingImage|StillImage|Archive|Score
  pages       INTEGER, -- nombre de pages (quand pertinent)
  size        INTEGER, -- in- : 8, 4, 12… peu fiable
  description TEXT, -- description dans la notice

  pers        BOOLEAN, -- auteur principal personne, redondant avec la jointure mais utile aux perfs
  birthyear   INTEGER, -- date de naissance de l’auteur principal, pour req antiquité ou sècles
  deathyear   INTEGER, -- date de mort de l’auteur principal, redondance
  posthum     BOOLEAN, -- si l’auteur principal est mort à la date d’édition
  gender      INTEGER, -- sexe de l’auteur principal

  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX document_ark ON document( ark );
CREATE INDEX document_type ON document( type, lang, date, pages );
CREATE INDEX document_date ON document( date, lang, type );
CREATE INDEX document_place ON document( place, type, lang, date, pages );
CREATE INDEX document_paris ON document( paris, type, lang, date, pages);
CREATE INDEX document_paris2 ON document( paris, type, date, pages);
CREATE INDEX document_pages ON document( pages, lang, date );
CREATE INDEX document_pages2 ON document( date, lang, pages  );
CREATE INDEX document_pages3 ON document( date, type, pages  );
CREATE INDEX document_posthum ON document( posthum, date, type, pages );
-- pour le graphe de répartition des siècles
CREATE INDEX document_birthyear ON document( date, type, posthum, birthyear );
-- pour le graphe latin et antiquité
CREATE INDEX document_birthyear2 ON document( date, type, birthyear, lang );
CREATE INDEX document_gender ON document( gender, date, type, lang, pages );
CREATE INDEX document_pers ON document( pers, date, type, lang  );

CREATE TABLE person (
  -- Autorité personne
  ark         TEXT NOT NULL, -- cote BNF
  name        TEXT NOT NULL, -- nom affichable
  family      TEXT NOT NULL, -- nom de famille
  given       TEXT, -- prénom
  sort        TEXT NOT NULL, -- version ASCII bas de casse du nom
  gender      INTEGER, -- inférence d’un sexe sur le prénom

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

  wites       BOOLEAN, -- cache, docs>0, efficace dans un index
  docs        INTEGER, -- cache, nombre de documents dont la personne est auteur principal
  posthum     INTEGER, -- cache, nombre de "docs" attribués après la mort
  anthum      INTEGER, -- cache, nombre de "docs" attribués avant la mort

  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);

CREATE UNIQUE INDEX person_ark ON person( ark );
CREATE INDEX person_sort ON person( sort, posthum );
CREATE INDEX person_birthyear ON person( birthyear, posthum );
CREATE INDEX person_birthyear2 ON person( birthyear, deathyear );
CREATE INDEX person_deathyear ON person( deathyear, birthyear );
CREATE INDEX person_posthum ON person( posthum, birthyear );
CREATE INDEX person_anthum ON person( anthum, birthyear );
CREATE INDEX person_docs ON person( docs, birthyear );
CREATE INDEX person_gender ON person( gender, writes, lang, birthyear );
CREATE INDEX person_writes ON person( writes, lang, birthyear, deathyear );


CREATE TABLE contribution (
  -- lien d’une personne à un document
  document     INTEGER REFERENCES document(id), -- lien au document par son rowid
  person       INTEGER REFERENCES person(id), -- lien à une œuvre, par son rowid
  role         INTEGER, -- nature de la responsabilité
  date         INTEGER, -- redondant avec la date de document, mais nécessaire
  posthum      BOOLEAN, -- document publié après la mort de l'auteur
  writes       BOOLEAN, -- redondant avec le code de rôle, mais efficace
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX contribution_document ON contribution( document, person, writes );
CREATE UNIQUE INDEX contribution_person ON contribution( person, document, writes );
CREATE INDEX contribution_posthum ON contribution( posthum, writes, date, person );
CREATE INDEX contribution_role ON contribution( role, person );
-- pour indexation person.docs, person.posthum, person.anthum
CREATE INDEX contribution_writes ON contribution( writes, person, date );
-- utile pour affecter document.deathyear, document.birthyear
CREATE INDEX contribution_writes2 ON contribution( writes, document, person );


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

CREATE TABLE creation (
  -- lien entre une œuvre et ses auteurs
  person       INTEGER REFERENCES person(id), -- lien à une personne, par son rowid
  work         INTEGER REFERENCES work(id), -- lien à l’œuvre, par son rowid
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX creation_work ON creation( work, person );
CREATE UNIQUE INDEX creation_person ON creation( person, work );

CREATE TABLE name (
  -- TODO, Noms de personnes, formes canonique et alternatives
  person      INTEGER REFERENCES person(id),
  label       TEXT,
  sort        TEXT,
  id          INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);

CREATE TABLE cachecurve (
  -- quand vraiment les index ne suffisent plus, mettre une courbe en cache
  year         INTEGER, -- année
  type         TEXT,    -- nom de la courbe
  value        REAL,    -- valeur
  id           INTEGER, -- rowid auto
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX cachegraph ON cachegraph( work, person );
