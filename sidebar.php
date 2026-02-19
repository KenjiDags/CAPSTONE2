<?php 
require 'auth.php';
require 'config.php';

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="sidebar">
        <a href="user_settings.php" class="logo-text">
            <div class="logo">
                <img src="images/tesda_logo.png">
                <h3>TESDA Inventory</h3>
            </div>
        </a>

            <?php
                     $dropdownActive = in_array($currentPage, ['inventory.php', 'ris.php', 'add_ris.php', 'view_ris.php', 'rsmi.php', 'SC.php', 'view_sc.php', 'rpci.php']);
                         $expendablesDropdownActive = in_array($currentPage, ['semi_expendible.php', 'PC.php', 'PC_semi.php', 'ics.php', 'itr.php', 'rspi.php', 'regspi.php', 'ict_registry.php', 'rpcsp.php', 'rrsp.php', 'iirusp.php']);
                $ppeDropdownActive = in_array($currentPage, ['PPE.php', 'add_ppe.php', 'edit_ppe.php', 'view_ppe.php', 'PPE_PC.php', 'PPE_PTR.php', 'add_ptr.php', 'edit_ptr.php', 'view_ptr.php', 'export_ptr.php', 'PPE_PAR.php', 'view_par.php', 'edit_par.php', 'export_par.php', 'RPCPPE.php']);

                ?>

                <nav>
                    <div class="dropdown <?= $dropdownActive ? 'open' : '' ?>">
                        <button class="dropdown-toggle <?= $dropdownActive ? 'active' : '' ?>">
                            🗂️ Office Supplies
                        </button>
                        <div class="dropdown-menu">
                            <a href="inventory.php" class="<?= $currentPage == 'inventory.php' ? 'active' : '' ?>">📋 Supply List</a>
                            <a href="ris.php" class="<?= in_array($currentPage, ['ris.php', 'add_ris.php', 'view_ris.php']) ? 'active' : '' ?>">📑 RIS</a>
                            <a href="rsmi.php" class="<?= $currentPage == 'rsmi.php' ? 'active' : '' ?>">📑 RSMI</a>
                            <a href="SC.php" class="<?= in_array($currentPage, ['SC.php', 'view_sc.php']) ? 'active' : '' ?>">📑 SC</a>
                            <a href="rpci.php" class="<?= $currentPage == 'rpci.php' ? 'active' : '' ?>">📑 RPCI</a>
                        </div>
                    </div>

                    <!-- Semi Expendables Dropdown -->
                    <div class="dropdown <?= $expendablesDropdownActive ? 'open' : '' ?>">
                        <button class="dropdown-toggle <?= $expendablesDropdownActive ? 'active' : '' ?>">
                            🗂️ Semi Expendables
                        </button>
                        <div class="dropdown-menu">
                            <a href="semi_expendible.php" class="<?= $currentPage == 'semi_expendible.php' ? 'active' : '' ?>">📋 Inventory List</a>
                            <a href="PC_semi.php" class="<?= $currentPage == 'PC_semi.php' ? 'active' : '' ?>">📄 SPC</a>
                            <a href="ics.php" class="<?= $currentPage == 'ics.php' ? 'active' : '' ?>">📦 ICS</a>
                            <a href="regspi.php" class="<?= $currentPage == 'regspi.php' ? 'active' : '' ?>">📦 RegSPI</a>
                            <a href="itr.php" class="<?= $currentPage == 'itr.php' ? 'active' : '' ?>">📦 ITR</a>
                            <a href="rspi.php" class="<?= $currentPage == 'rspi.php' ? 'active' : '' ?>">📦 RSPI</a>
                            <a href="rpcsp.php" class="<?= $currentPage == 'rpcsp.php' ? 'active' : '' ?>">📄 RPCSP</a>
                            <a href="rrsp.php" class="<?= $currentPage == 'rrsp.php' ? 'active' : '' ?>">📄 RRSP</a>
                            <a href="iirusp.php" class="<?= $currentPage == 'iirusp.php' ? 'active' : '' ?>">📄 IIRUSP</a>
                        </div>
                    </div>

                    <!-- PPE Dropdown -->
                    <div class="dropdown <?= $ppeDropdownActive ? 'open' : '' ?>">
                        <button class="dropdown-toggle <?= $ppeDropdownActive ? 'active' : '' ?>">
                            🗂️ PPE
                        </button>
                        <div class="dropdown-menu">
                            <a href="PPE.php" class="<?= $currentPage == 'PPE.php' ? 'active' : '' ?>">📋 Inventory List</a>
                            <a href="PPE_PC.php" class="<?= $currentPage == 'PPE_PC.php' ? 'active' : '' ?>">📄 PC</a>
                            <a href="PPE_PTR.php" class="<?= $currentPage == 'PPE_PTR.php' ? 'active' : '' ?>">📄 PTR</a>
                            <a href="PPE_PAR.php" class="<?= $currentPage == 'PPE_PAR.php' ? 'active' : '' ?>">📄 PAR</a>
                            <a href="RPCPPE.php" class="<?= $currentPage == 'RPCPPE.php' ? 'active' : '' ?>">📄 RPCPPE</a>
                        </div>
                    </div>

                    <a href="analytics.php" class="no-italic <?= $currentPage == 'analytics.php' ? 'active' : '' ?>">
                        📊 Analytics
                    </a>

                </nav>

                    <!-- Logout -->
                    <div class="logout-wrapper">
                        <a href="logout.php" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                
        </div>

    <script src="js/password.js?v=<?= time() ?>"></script>
    <script src="js/sidebar_script.js?v=<?= time() ?>"></script>
</body>
</html>
