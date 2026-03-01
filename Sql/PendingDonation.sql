IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='PendingDonations' and xtype='U')
CREATE TABLE PendingDonations (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    AccountID VARCHAR(50) NOT NULL,
    CreditsToReceive INT NOT NULL,
    ReferenceNumber VARCHAR(100) NOT NULL,
    ProofImage VARCHAR(255) NOT NULL,
    DateSubmitted DATETIME DEFAULT GETDATE(),
    Status TINYINT DEFAULT 0 
);