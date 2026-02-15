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
</head>
<body>
    <div class="sidebar">
        <a href="inventory.php" class="logo-text">
            <div class="logo">
                <img src="images/tesda_logo.png">
                <h3>Tesda Inventory</h3>
            </div>
        </a>

            <?php
                     $dropdownActive = in_array($currentPage, ['inventory.php', 'ris.php', 'add_ris.php', 'view_ris.php', 'rsmi.php', 'SC.php', 'view_sc.php', 'rpci.php']);
                         $expendablesDropdownActive = in_array($currentPage, ['semi_expendible.php', 'PC.php', 'PC_semi.php', 'ics.php', 'itr.php', 'rspi.php', 'regspi.php', 'ict_registry.php', 'rpcsp.php', 'rrsp.php', 'iirusp.php']);
                $ppeDropdownActive = in_array($currentPage, ['PPE.php', 'add_ppe.php', 'edit_ppe.php', 'view_ppe.php', 'PPE_PC.php']);

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
                            🧰 Semi Expendables
                        </button>
                        <div class="dropdown-menu">
                            <a href="semi_expendible.php" class="<?= $currentPage == 'semi_expendible.php' ? 'active' : '' ?>">📋 Inventory List</a>
                            <a href="PC_semi.php" class="<?= $currentPage == 'PC_semi.php' ? 'active' : '' ?>">📇 SPC</a>
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
                            🛠️ PPE
                        </button>
                        <div class="dropdown-menu">
                            <a href="PPE.php" class="<?= $currentPage == 'PPE.php' ? 'active' : '' ?>">📋 Inventory List</a>
                            <a href="PPE_PC.php" class="<?= $currentPage == 'PPE_PC.php' ? 'active' : '' ?>">📄 PC</a>
                        </div>
                    </div>

                    <div class="home">
                        <a href="analytics.php"> Home </a>
                    </div>

                </nav>

                    <!-- Change Password -->
                    <div class="logout-wrapper">
                        <a href="#" class="change-password-btn" id="openChangePassword">Change Password</a>
                        <a href="logout.php" class="logout-btn">Logout</a>
                    </div>
                
        </div>

        <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeChangePassword">&times;</span>
            <h3>Change Password</h3>
            
            <form id="changePasswordForm" method="post">
                <div class="password-field" style="position: relative;">
                    <input type="password" name="currentPassword" id="currentPassword" placeholder="Current Password" required>
                    <span class="show-password-toggle" onclick="togglePassword('currentPassword')">Show</span>
                </div>

                <div class="password-field" style="position: relative;">
                    <input type="password" name="newPassword" id="newPassword" placeholder="New Password" required>
                    <span class="show-password-toggle" onclick="togglePassword('newPassword')">Show</span>
                </div>

                <div class="password-field" style="position: relative;">
                    <input type="password" name="confirmPassword" id="confirmPassword" placeholder="Confirm New Password" required>
                    <span class="show-password-toggle" onclick="togglePassword('confirmPassword')">Show</span>
                </div>
                    <button type="submit" class="save-btn">Save</button> 
            </form>
            <div id="changePasswordMessage" style="margin-top:15px; text-align:center; font-weight:600;"></div>
        </div>
    </div>

    <script src="js/password.js?v=<?= time() ?>"></script>
    <script src="js/sidebar_script.js?v=<?= time() ?>"></script>
</body>
</html>
