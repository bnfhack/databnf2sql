# databnf2sql

La BNF expose la totalité de son catalogue sous la forme d’enregistrements RDF. 
Il est possible de parcourir le réseau à travers l’Internet mais dès que l’on souhaite des statistiques plus générales,
le temps de parcours est trop long, il vaut mieux rappatrier la totalité des données localement.
La BNF offre aussi un dump complet de ses notices : ftp://databnf:databnf@echanges.bnf.fr/
C’est une ressource documentaire extraordinaire, merci le service public.
Le code ici a été utilisé pour extraire les données des fichiers.
Ce n’est pas une application distribuable, plutôt une mémoire technique.
Les personnes intéressées peuvent commencer par télécharger un état de la base avec les données BNF d’avril 2016
http://obvil.lip6.fr/cataviz/databnf.db
Le schéma de la base est ici http://github.com/bnfhack/databnf2sql/blob/master/databnf.sql
On trouvera aussi un exemple d’utilisation avec cataviz  https://github.com/bnfhack/cataviz (démo : http://obvil.lip6.fr/cataviz/, 
doc : http://resultats.hypotheses.org/795)
