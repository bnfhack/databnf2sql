# databnf2sql

La BNF expose la plus grande partie de son catalogue sous la forme d’enregistrements RDF, dans <http://data.bnf.fr/>. 
Il est possible de parcourir le réseau à travers l’Internet, mais dès que l’on souhaite des statistiques plus générales,
le temps de parcours est trop long, il vaut mieux rappatrier la totalité des données localement.
La BNF offre ainsi un export complet des notices : <http://echanges.bnf.fr/PIVOT/?user=databnf&password=databnf>

Ce paquet de code est utilisé pour projeter le labyrinthe RDF dans une base de données SQL, beaucoup plus efficace.
Ce n’est pas une application distribuable, plutôt une mémoire technique.
Les personnes intéressées peuvent commencer par télécharger un état de la base avec les données databnf de mars 2017
http://obvil.lip6.fr/cataviz/databnf.db
Le schéma de la base est ici http://github.com/bnfhack/databnf2sql/blob/master/databnf.sql
On trouvera aussi un exemple d’utilisation avec cataviz  https://github.com/bnfhack/cataviz (démo : http://obvil.lip6.fr/cataviz/, 
doc : http://resultats.hypotheses.org/795)
