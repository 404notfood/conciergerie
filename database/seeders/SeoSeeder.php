<?php

namespace Database\Seeders;

use App\Models\SeoPage;
use App\Models\SeoSetting;
use Illuminate\Database\Seeder;

class SeoSeeder extends Seeder
{
    public function run(): void
    {
        SeoSetting::firstOrCreate(['id' => 1]);
        /** @var array<int, array<string, mixed>> $pages */
        $pages = json_decode(<<<'JSON'
[
  {
    "path": "/",
    "kind": "home",
    "title": "Logiciel de conciergerie : missions et équipes | {app_name}",
    "description": "Organisez vos missions, vos équipes et vos lieux d’intervention. Contrôlez les comptes rendus avec photos et partagez un suivi validé avec vos clients.",
    "heading": "Toute votre conciergerie, organisée au même endroit.",
    "intro": "Planifiez vos missions, coordonnez vos équipes et partagez des comptes rendus clairs avec vos clients.",
    "keyword": "logiciel de conciergerie",
    "sections": [
      {
        "heading": "Un logiciel pour suivre toute l’intervention",
        "text": "{app_name} relie vos clients, leurs lieux et les personnes chargées des interventions. Le gestionnaire planifie une mission, choisit une checklist et attribue le travail à une personne ou à une entreprise partenaire. L’intervenant accepte sa mission, réalise les tâches et transmet un compte rendu avec ses photos. Le gestionnaire contrôle ce compte rendu avant de le rendre accessible au client. Les accès aux biens restent réservés aux personnes autorisées."
      }
    ],
    "faq": [
      {
        "question": "À qui s’adresse cette application de conciergerie ?",
        "answer": "Elle accompagne les conciergeries qui organisent des interventions pour des locations saisonnières, des résidences ou des prestations de service. Chaque espace regroupe ses propres équipes, clients, lieux et missions."
      },
      {
        "question": "Peut-on travailler sans connexion ?",
        "answer": "L’espace terrain permet de préparer les missions acceptées avant de partir. Les brouillons et photos peuvent être conservés sur l’appareil puis envoyés au retour du réseau. La préparation initiale et l’envoi nécessitent une connexion ; l’option doit être incluse dans l’abonnement."
      },
      {
        "question": "Le client voit-il toutes les informations internes ?",
        "answer": "Le client voit les éléments qui lui sont destinés : ses lieux, ses demandes, ses échanges et les comptes rendus validés. Les accès confidentiels et les notes internes ne sont pas publiés dans son espace."
      }
    ],
    "status": "published",
    "noindex": false
  },
  {
    "path": "/tarifs",
    "kind": "pricing",
    "title": "Tarifs et options du logiciel de conciergerie | {app_name}",
    "description": "Découvrez les offres de gestion de conciergerie, leurs capacités pour vos équipes, clients et lieux, et les fonctionnalités incluses dans chaque abonnement.",
    "heading": "Une offre adaptée à votre conciergerie.",
    "intro": "Des capacités claires, avec les options utiles à votre activité.",
    "keyword": "tarif logiciel conciergerie",
    "sections": [
      {
        "heading": "Choisir les capacités utiles à votre organisation",
        "text": "Comparez le nombre de collaborateurs, de clients et de lieux d’intervention, les équipes prestataires, le volume mensuel de missions et l’espace disponible pour les photos. Les fonctionnalités incluses sont indiquées dans chaque offre publiée. Les options et ajustements de votre abonnement sont visibles dans votre espace. Les conditions applicables sont enregistrées lors de son attribution."
      }
    ],
    "faq": [
      {
        "question": "Où retrouver les limites de mon abonnement ?",
        "answer": "Le responsable de la conciergerie retrouve les capacités et la consommation de son espace dans la rubrique Abonnement. Les limites sont contrôlées au moment de créer de nouvelles données."
      }
    ],
    "status": "published",
    "noindex": false
  },
  {
    "path": "/ressources",
    "kind": "hub",
    "title": "Guides et fonctionnalités pour votre conciergerie | {app_name}",
    "description": "Explorez le planning, le travail sur le terrain et le suivi client. Des guides concrets pour organiser les interventions et les comptes rendus de votre conciergerie.",
    "heading": "Des repères pour organiser votre conciergerie.",
    "intro": "Découvrez les fonctions de la plateforme et des méthodes concrètes pour accompagner vos équipes, de la préparation d’une mission au compte rendu validé.",
    "keyword": "gestion conciergerie",
    "sections": [],
    "faq": [],
    "status": "published",
    "noindex": false
  },
  {
    "path": "/fonctionnalites/planning-missions",
    "title": "Planning des missions de conciergerie | {app_name}",
    "description": "Planifiez les interventions de votre conciergerie : lieux, horaires, checklists et attribution aux équipes. Suivez l’acceptation et la progression de chaque mission.",
    "heading": "Un planning relié aux lieux et aux équipes.",
    "intro": "Une intervention réussie commence par une consigne claire. Regroupez le lieu, l’horaire, les tâches attendues et la personne chargée de les réaliser dans une mission.",
    "keyword": "planning conciergerie",
    "sections": [
      {
        "heading": "Préparer une mission exploitable sur le terrain",
        "text": "Créez d’abord votre client et son lieu d’intervention. Vous pouvez ensuite planifier une mission avec un horaire, des consignes et une checklist adaptée au service. La mission conserve les tâches choisies au moment de sa préparation : une évolution ultérieure du modèle ne modifie pas discrètement ce qui a été demandé. Cette préparation aide l’intervenant à savoir où aller, ce qu’il doit vérifier et quelles preuves sont attendues."
      },
      {
        "heading": "Attribuer le travail à une personne ou à un partenaire",
        "text": "Le responsable peut confier la mission à un membre de son équipe ou à une entreprise prestataire. Dans ce second cas, le coordinateur désigne la personne qui interviendra réellement. La mission doit être acceptée par cette personne. Le planning distingue ainsi une attribution prévue, une acceptation et un travail réalisé. Les accès confidentiels du lieu sont transmis dans le périmètre autorisé, après acceptation."
      },
      {
        "heading": "Contrôler les résultats avant de les partager",
        "text": "L’intervenant transmet son compte rendu et les photos associées. Le responsable peut valider le résultat ou demander une reprise. Le client accède au compte rendu après validation. Cette étape évite de confondre la fin d’un passage avec le contrôle de sa qualité. Si les consignes changent pendant le travail, la version de la mission est vérifiée à l’envoi afin de signaler un décalage et de conserver les éléments à examiner."
      }
    ],
    "faq": [
      {
        "question": "La plateforme attribue-t-elle automatiquement les missions ?",
        "answer": "L’attribution et la désignation de l’intervenant sont effectuées par les personnes responsables. Le logiciel enregistre ces décisions et permet de suivre leur état."
      },
      {
        "question": "Puis-je importer un calendrier de réservations ?",
        "answer": "Un fichier de calendrier ICS peut être importé pour enregistrer les séjours et repérer les périodes à organiser. L’import se fait à votre initiative ; il ne crée pas automatiquement des missions ni une synchronisation permanente."
      }
    ],
    "kind": "content",
    "status": "published",
    "noindex": false
  },
  {
    "path": "/fonctionnalites/application-terrain",
    "title": "Application terrain et missions hors connexion | {app_name}",
    "description": "Préparez les missions acceptées de votre équipe, enregistrez checklists et photos hors connexion, puis retrouvez les envois en attente au retour du réseau.",
    "heading": "Accompagnez vos équipes sur le terrain.",
    "intro": "Les intervenants retrouvent leurs missions et leurs consignes dans un espace adapté au mobile. La préparation hors connexion permet de conserver les observations pendant un passage.",
    "keyword": "application terrain conciergerie",
    "sections": [
      {
        "heading": "Préparer la journée avant de partir",
        "text": "La personne se connecte à son compte, accepte ses missions puis prépare les données nécessaires tant que le réseau est disponible. Seules les missions autorisées de ce compte sont concernées. Un code local protège les informations enregistrées sur l’appareil. Cette préparation reste indispensable : une première visite sans réseau ne peut pas télécharger des consignes qui n’ont jamais été chargées."
      },
      {
        "heading": "Conserver les tâches, observations et preuves photo",
        "text": "Pendant une intervention préparée, l’intervenant renseigne les éléments de la checklist, ajoute ses observations et conserve ses photos sur l’appareil. Les brouillons doivent être enregistrés. L’espace terrain affiche les travaux en attente pour permettre de reprendre une saisie ou un envoi. La disponibilité de cet espace dépend des fonctionnalités incluses dans l’abonnement de la conciergerie."
      },
      {
        "heading": "Vérifier les envois au retour de la connexion",
        "text": "Quand la connexion revient, l’intervenant peut envoyer les éléments conservés et consulter leur état. Une reprise d’envoi ne doit pas créer plusieurs comptes rendus pour la même opération. Si le responsable a modifié la mission entre-temps, un conflit est signalé et les preuves restent disponibles pour être examinées. Il faut vérifier la réception avant de supprimer les éléments locaux ou de changer d’appareil."
      }
    ],
    "faq": [
      {
        "question": "L’envoi est-il assuré lorsque le téléphone est éteint ?",
        "answer": "Non. Le travail reste conservé sur l’appareil et l’intervenant doit revenir dans l’espace terrain avec une connexion pour envoyer et vérifier la réception."
      }
    ],
    "kind": "content",
    "status": "published",
    "noindex": false
  },
  {
    "path": "/fonctionnalites/espace-client",
    "title": "Espace client de votre conciergerie | {app_name}",
    "description": "Donnez à vos clients un espace pour suivre leurs lieux, demander une intervention et consulter les comptes rendus avec photos après validation du responsable.",
    "heading": "Un suivi clair pour chacun de vos clients.",
    "intro": "Un client doit comprendre ce qui a été réalisé sans accéder aux échanges internes de votre équipe. Son espace rassemble les informations que votre conciergerie lui destine.",
    "keyword": "espace client conciergerie",
    "sections": [
      {
        "heading": "Regrouper les lieux et les demandes du client",
        "text": "Chaque client est associé à ses propres lieux. Il peut déposer une demande d’intervention dans son espace. Le responsable examine cette demande, accepte ou refuse la prise en charge puis organise la mission si elle est retenue. Une demande ne devient donc pas automatiquement une intervention confirmée : cette décision reste sous le contrôle de la conciergerie."
      },
      {
        "heading": "Publier des comptes rendus après contrôle",
        "text": "Une fois le travail effectué, le responsable examine le compte rendu et ses photos. Le client consulte les éléments validés qui concernent ses biens. Les brouillons de terrain, les codes d’accès et les notes réservées à l’équipe restent dans leur périmètre interne. Cette séparation permet de partager un suivi utile sans transférer toutes les données opérationnelles."
      },
      {
        "heading": "Partager un incident avec une explication adaptée",
        "text": "L’équipe peut signaler un incident lors d’un passage. Le responsable choisit ce qu’il transmet au client : une explication et les photos pertinentes. La sélection est explicite pour éviter de publier un dossier interne entier. Les échanges dans la messagerie restent rattachés aux personnes et au périmètre du client concerné."
      }
    ],
    "faq": [
      {
        "question": "Les clients peuvent-ils voir les lieux des autres clients ?",
        "answer": "Non. L’accès est limité aux lieux et éléments associés au client connecté dans la conciergerie concernée."
      }
    ],
    "kind": "content",
    "status": "published",
    "noindex": false
  },
  {
    "path": "/solutions/location-saisonniere",
    "title": "Logiciel pour conciergerie de location saisonnière | {app_name}",
    "description": "Organisez les ménages, les séjours et les interventions de vos locations saisonnières. Coordonnez vos prestataires et contrôlez les preuves photo avant partage.",
    "heading": "Organisez les interventions entre deux séjours.",
    "intro": "La gestion d’une location saisonnière réunit des horaires, des personnes et des contrôles. Reliez le séjour au lieu, puis préparez les passages nécessaires.",
    "keyword": "logiciel conciergerie location saisonnière",
    "sections": [
      {
        "heading": "Centraliser les lieux et les périodes de séjour",
        "text": "Enregistrez les logements de vos clients avec les informations utiles aux intervenants. Ajoutez les séjours manuellement ou importez un fichier ICS. Les périodes importées permettent d’identifier les moments à organiser, tandis que les périodes à qualifier restent visibles. Cet import est une opération volontaire ; il ne remplace pas une synchronisation directe et continue avec une plateforme de réservation."
      },
      {
        "heading": "Préparer les ménages et les interventions nécessaires",
        "text": "À partir des périodes connues, planifiez les missions : ménage, vérification d’équipements, remise en état ou passage technique. Choisissez une checklist adaptée et attribuez le travail à une personne ou à une entreprise partenaire. L’acceptation et le statut de la mission permettent de distinguer ce qui est prévu de ce qui est effectivement pris en charge. Vous gardez la main sur les horaires et les personnes."
      },
      {
        "heading": "Documenter le passage et le suivi du propriétaire",
        "text": "Le compte rendu relie les tâches réalisées aux photos conservées pendant la mission. Le responsable contrôle les éléments avant de les rendre accessibles au client. Si un dommage ou un manque est signalé, l’incident peut être expliqué avec une sélection de photos. Le propriétaire retrouve ainsi un suivi des interventions qui concernent ses biens, sans recevoir les consignes confidentielles de l’équipe."
      }
    ],
    "faq": [
      {
        "question": "Cette application remplace-t-elle un moteur de réservation ?",
        "answer": "Elle organise les interventions et le suivi des lieux. Elle ne propose pas de moteur de réservation, de paiement des voyageurs ni de synchronisation automatique avec les plateformes de location."
      }
    ],
    "kind": "content",
    "status": "published",
    "noindex": false
  },
  {
    "path": "/guides/organiser-equipe-conciergerie",
    "title": "Comment organiser une équipe de conciergerie ? | {app_name}",
    "description": "Une méthode pour préparer les missions, définir les responsabilités, organiser les prestataires et contrôler les comptes rendus d’une équipe de conciergerie.",
    "heading": "Organiser une équipe de conciergerie, étape par étape.",
    "intro": "Pour coordonner plusieurs personnes, rendez visibles trois choses : le travail attendu, la personne qui le prend en charge et le contrôle qui confirme le résultat.",
    "keyword": "organiser équipe conciergerie",
    "sections": [
      {
        "heading": "Définir les responsabilités avant de remplir le planning",
        "text": "Identifiez qui prépare les missions, qui les réalise et qui valide les comptes rendus. Un responsable doit pouvoir arbitrer les changements d’horaire et les reprises. Pour une entreprise prestataire, distinguez le coordinateur de la personne qui se déplace. Évitez de considérer une mission comme acceptée simplement parce qu’elle a été attribuée à une entreprise : confirmez la personne désignée et son acceptation."
      },
      {
        "heading": "Rédiger des consignes qui peuvent être vérifiées",
        "text": "Décrivez le lieu, la plage horaire, les tâches et les observations attendues. Transformez les points essentiels en checklist : état des pièces, équipements à contrôler, éléments à remettre en place. Précisez les photos utiles, sans demander des images qui ne servent pas au contrôle. Une consigne courte et concrète est plus facile à utiliser sur téléphone qu’un long message qui mélange toutes les interventions de la journée."
      },
      {
        "heading": "Prévoir le contrôle et la gestion des écarts",
        "text": "Réservez un moment pour examiner les comptes rendus. Quand une preuve manque, indiquez la reprise attendue. Pour un incident, préparez une explication destinée au client et sélectionnez les éléments nécessaires. Si un lieu ou une consigne évolue pendant la mission, vérifiez la version utilisée par l’intervenant avant de conclure. Une transmission structurée évite de perdre le suivi dans des conversations séparées."
      },
      {
        "heading": "Relier cette méthode à votre espace de travail",
        "text": "Dans {app_name}, vous créez les clients et les lieux, ajoutez les membres et les partenaires, puis préparez les missions avec leurs modèles de service. L’espace terrain conserve les observations de l’intervenant. Le responsable contrôle le compte rendu et le client consulte les éléments validés. Commencez par quelques interventions représentatives pour ajuster vos checklists à la réalité de votre activité."
      }
    ],
    "faq": [],
    "kind": "content",
    "status": "published",
    "noindex": false
  }
]
JSON, true, 512, JSON_THROW_ON_ERROR);
        foreach ($pages as $page) {
            SeoPage::firstOrCreate(['path' => $page['path']], [...$page, 'published_at' => now()]);
        }
    }
}
