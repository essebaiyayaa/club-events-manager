<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';
require_once 'C:/xampp/htdocs/campusEvents/vendor/autoload.php'; // Pour TCPDF et PHPMailer

use TCPDF;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Vérifier l'authentification
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$id_evenement = $_POST['id_evenement'] ?? null;

if (!$id_evenement) {
    echo json_encode(['success' => false, 'message' => 'ID événement manquant']);
    exit();
}

try {
    // Récupérer les informations de l'événement
    $event_query = "SELECT e.*, u.nom as org_nom, u.prenom as org_prenom, c.nom_club
                    FROM Evenement e
                    JOIN Utilisateur u ON e.id_organisateur = u.id_utilisateur
                    LEFT JOIN Club c ON e.id_club = c.id_club
                    WHERE e.id_evenement = ? AND e.id_organisateur = ?";
    $event_stmt = $pdo->prepare($event_query);
    $event_stmt->execute([$id_evenement, $_SESSION['user_id']]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Événement non trouvé']);
        exit();
    }

    // Récupérer les participants validés
    $participants_query = "SELECT i.id_inscription, u.id_utilisateur, u.nom, u.prenom, u.email
                           FROM Inscription i
                           JOIN Utilisateur u ON i.id_utilisateur = u.id_utilisateur
                           WHERE i.id_evenement = ? AND i.status = 'validée'";
    $participants_stmt = $pdo->prepare($participants_query);
    $participants_stmt->execute([$id_evenement]);
    $participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($participants)) {
        echo json_encode(['success' => false, 'message' => 'Aucun participant validé']);
        exit();
    }

    $sent_count = 0;
    $attestations_dir = 'C:/xampp/htdocs/campusEvents/uploads/attestations/';

    // Créer le dossier s'il n'existe pas
    if (!file_exists($attestations_dir)) {
        mkdir($attestations_dir, 0777, true);
    }

    foreach ($participants as $participant) {
        // Générer le PDF
        $pdf_path = generateAttestationPDF($participant, $event, $attestations_dir);

        if ($pdf_path) {
            // Envoyer l'email
            if (sendAttestationEmail($participant, $event, $pdf_path)) {
                // Enregistrer dans la base de données
                $insert_query = "INSERT INTO Attestation (chemin_pdf, id_inscription, date_generation) 
                                 VALUES (?, ?, NOW())
                                 ON DUPLICATE KEY UPDATE chemin_pdf = VALUES(chemin_pdf), date_generation = NOW()";
                $insert_stmt = $pdo->prepare($insert_query);
                $insert_stmt->execute([basename($pdf_path), $participant['id_inscription']]);
                $sent_count++;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Attestations générées et envoyées avec succès',
        'sent' => $sent_count,
        'total' => count($participants)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}

/* --------------------------------------------------------
   🔹 FONCTION : GÉNÉRATION DU PDF
-------------------------------------------------------- */
/* --------------------------------------------------------
   🔹 FONCTION : GÉNÉRATION DU PDF AVEC DESIGN DORÉ ÉLÉGANT
-------------------------------------------------------- */
function generateAttestationPDF($participant, $event, $output_dir) {
    try {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('CampusEvent');
        $pdf->SetAuthor('CampusEvent');
        $pdf->SetTitle('Attestation de Participation');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        // 🎨 Nouvelle palette dorée
        $gold = [212, 175, 55];        // Or vif
        $light_gold = [245, 230, 150]; // Doré clair brillant
        $dark = [44, 62, 80];          // Bleu-gris profond (neutre)
        $gray = [108, 117, 125];       // Gris texte

        // ===== Décorations dorées (coins) =====
        $pdf->SetDrawColor($gold[0], $gold[1], $gold[2]);
        $pdf->SetLineWidth(0.3);

        // Coin supérieur gauche
        for ($i = 0; $i < 15; $i++) {
            $offset = $i * 2;
            $pdf->Curve(10 + $offset, 10, 20 + $offset, 15, 15 + $offset, 25, 10 + $offset, 35);
        }

        // Coin supérieur droit
        for ($i = 0; $i < 12; $i++) {
            $offset = $i * 2;
            $pdf->Curve(287 - $offset, 10, 277 - $offset, 15, 282 - $offset, 25, 287 - $offset, 35);
        }

        // Coin inférieur droit
        for ($i = 0; $i < 15; $i++) {
            $offset = $i * 2;
            $pdf->Curve(287 - $offset, 200, 277 - $offset, 195, 282 - $offset, 185, 287 - $offset, 175);
        }

        // ===== TITRE PRINCIPAL =====
        $pdf->SetFont('helvetica', 'B', 48);
        $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
        $pdf->SetXY(0, 40);
        $pdf->Cell(297, 15, 'ATTESTATION', 0, 1, 'C');

        // Sous-titre doré
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetTextColor($gold[0], $gold[1], $gold[2]);
        $pdf->SetXY(0, 58);
        $pdf->Cell(297, 10, 'DE PARTICIPATION', 0, 1, 'C');

        // Ligne dorée
        $pdf->SetDrawColor($gold[0], $gold[1], $gold[2]);
        $pdf->SetLineWidth(0.6);
        $pdf->Line(80, 75, 217, 75);
        $pdf->Circle(80, 75, 1.8, 0, 360, 'F');
        $pdf->Circle(217, 75, 1.8, 0, 360, 'F');

        // ===== TEXTE "CETTE ATTESTATION EST DÉCERNÉ À" =====
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
        $pdf->SetXY(0, 82);
        $pdf->Cell(297, 6, 'CETTE ATTESTATION EST DÉCERNÉE À', 0, 1, 'C');

        // ===== NOM DU PARTICIPANT =====
        $participant_name = strtoupper($participant['prenom'] . ' ' . $participant['nom']);
        $pdf->SetFont('helvetica', 'B', 40);
        $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
        $pdf->SetXY(0, 95);
        $pdf->Cell(297, 18, $participant_name, 0, 1, 'C');

        // Ligne sous le nom
        $pdf->SetDrawColor($gold[0], $gold[1], $gold[2]);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(60, 118, 237, 118);
        $pdf->Circle(60, 118, 1.5, 0, 360, 'F');
        $pdf->Circle(237, 118, 1.5, 0, 360, 'F');

        // ===== TEXTE DE REMERCIEMENT =====
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
        $pdf->SetXY(40, 128);
        $event_name = $event['titre'];
        $club_name = $event['nom_club'] ?? 'CampusEvent';
        $thank_text = "Merci d'avoir participé à l'événement '$event_name'";
        $pdf->MultiCell(217, 7, $thank_text, 0, 'C');

        // ===== MÉDAILLE DORÉE =====
        $centerX = 148.5;
        $centerY = 155;
        $radius = 12;

        // Cercle extérieur doré
        $pdf->SetFillColor($gold[0], $gold[1], $gold[2]);
        $pdf->Circle($centerX, $centerY, $radius, 0, 360, 'F');

        // Cercle intérieur plus clair
        $pdf->SetFillColor($light_gold[0], $light_gold[1], $light_gold[2]);
        $pdf->Circle($centerX, $centerY, $radius - 3, 0, 360, 'F');

        // Centre légèrement plus foncé (effet métal)
        $pdf->SetFillColor($gold[0] - 25, $gold[1] - 25, $gold[2] - 25);
        $pdf->Circle($centerX, $centerY, $radius - 6, 0, 360, 'F');

        // Rubans
        $pdf->SetFillColor($gold[0], $gold[1], $gold[2]);
        $pdf->Rect($centerX - 6, $centerY + 8, 3, 15, 'F');
        $pdf->Rect($centerX + 3, $centerY + 8, 3, 15, 'F');

        // Pointes rubans
        $pdf->SetDrawColor($gold[0], $gold[1], $gold[2]);
        $pdf->SetFillColor($gold[0], $gold[1], $gold[2]);
        $points1 = [$centerX - 6, $centerY + 23, $centerX - 4.5, $centerY + 26, $centerX - 3, $centerY + 23];
        $points2 = [$centerX + 3, $centerY + 23, $centerX + 4.5, $centerY + 26, $centerX + 6, $centerY + 23];
        $pdf->Polygon($points1, 'F');
        $pdf->Polygon($points2, 'F');

        // ===== SIGNATURES =====
        $sigY = 178;

        // Organisateur
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
        $pdf->SetXY(50, $sigY);
        $pdf->Cell(80, 5, 'Organisateur', 0, 0, 'C');
        $pdf->SetDrawColor($gold[0], $gold[1], $gold[2]);
        $pdf->Line(60, $sigY + 8, 120, $sigY + 8);
        $org_name = ($event['org_prenom'] ?? '') . ' ' . ($event['org_nom'] ?? '');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($gold[0], $gold[1], $gold[2]);
        $pdf->SetXY(50, $sigY + 10);
        $pdf->Cell(80, 5, trim($org_name) ?: 'Nom Organisateur', 0, 0, 'C');

        // Club
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
        $pdf->SetXY(167, $sigY);
        $pdf->Cell(80, 5, 'Club', 0, 0, 'C');
        $pdf->Line(177, $sigY + 8, 237, $sigY + 8);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($gold[0], $gold[1], $gold[2]);
        $pdf->SetXY(167, $sigY + 10);
        $pdf->Cell(80, 5, $club_name ?: 'Nom Club', 0, 0, 'C');

        // ===== SAUVEGARDE =====
        $filename = 'attestation_' . $participant['id_utilisateur'] . '_' . $event['id_evenement'] . '_' . time() . '.pdf';
        $filepath = $output_dir . $filename;
        $pdf->Output($filepath, 'F');

        return $filepath;

    } catch (Exception $e) {
        error_log("Erreur génération PDF: " . $e->getMessage());
        return false;
    }
}
/* --------------------------------------------------------
   🔹 FONCTION : ENVOI DE L’EMAIL AVEC ATTESTATION
-------------------------------------------------------- */
function sendAttestationEmail($participant, $event, $pdf_path) {
    try {
        $mail = new PHPMailer(true);

        // ⚙️ Configuration SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'essebaiyaya@gmail.com'; // 👉 à remplacer
        $mail->Password = 'lwuv exow molb bnnk'; // 👉 mot de passe d’application Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        // 📧 Expéditeur & destinataire
        $mail->setFrom('essebaiyaya@gmail.om', 'CampusEvent');
        $mail->addAddress($participant['email'], $participant['prenom'] . ' ' . $participant['nom']);
        $mail->addAttachment($pdf_path);

        // 📝 Contenu
        $mail->isHTML(true);
        $mail->Subject = 'Votre attestation de participation - ' . $event['titre'];
        $mail->Body = "
            <html>
                <body style='font-family: Arial, sans-serif; color: #333;'>
                    <h2>Bonjour {$participant['prenom']},</h2>
                    <p>Merci d'avoir participé à <strong>{$event['titre']}</strong> organisé par le club 
                    <strong>" . ($event['nom_club'] ?? 'CampusEvent') . "</strong>.</p>
                    <p>Vous trouverez ci-joint votre attestation de participation au format PDF.</p>
                    <br>
                    <p>Cordialement,<br><strong>L'équipe CampusEvent</strong></p>
                </body>
            </html>
        ";

        $mail->AltBody = "Bonjour {$participant['prenom']},\n\nMerci d'avoir participé à {$event['titre']}.\nVotre attestation est en pièce jointe.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur email à {$participant['email']}: " . $mail->ErrorInfo);
        return false;
    }
}