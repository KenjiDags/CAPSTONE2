-- PAR issuance needs an explicit Issued status; retain existing ENUM order.
ALTER TABLE ppe_property
    MODIFY COLUMN status ENUM('Active', 'Transferred', 'Returned', 'For Repair', 'Unserviceable', 'Disposed', 'Issued') DEFAULT 'Active';
