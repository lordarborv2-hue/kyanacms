USE [MuOnline] -- Change this to your actual database name if it is different
GO

-- ====================================================================
-- 1. CREATE WEBCREDITS TABLE
-- Holds the Webshop currency for each user account.
-- ====================================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[WebCredits]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[WebCredits](
        [memb___id] [varchar](10) NOT NULL,
        [credits] [int] NOT NULL DEFAULT (0),
        CONSTRAINT [PK_WebCredits] PRIMARY KEY CLUSTERED 
        (
            [memb___id] ASC
        )
    )
    PRINT 'Created table: WebCredits'
END
ELSE
BEGIN
    PRINT 'Table WebCredits already exists.'
END
GO

-- ====================================================================
-- 2. CREATE WEBSHOP ITEMS TABLE
-- Holds all the item data, sizes, limits, and auto-detected flags.
-- ====================================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[WebshopItems]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[WebshopItems](
        [ItemType] [int] NOT NULL,
        [ItemIndex] [int] NOT NULL,
        [ItemName] [varchar](100) NULL,
        [Width] [int] NOT NULL DEFAULT (1),
        [Height] [int] NOT NULL DEFAULT (1),
        [BasePrice] [int] NOT NULL DEFAULT (100),
        [AllowExc] [bit] NOT NULL DEFAULT (1),
        [AllowLevel] [bit] NOT NULL DEFAULT (1),
        [Allow380] [bit] NOT NULL DEFAULT (0),
        [AllowHarmony] [bit] NOT NULL DEFAULT (1),
        [AllowSocket] [bit] NOT NULL DEFAULT (0),
        [MaxExc] [int] NOT NULL DEFAULT (6),
        [MaxSocket] [int] NOT NULL DEFAULT (0),
        [AllowLuck] [bit] NOT NULL DEFAULT (1),
        [AllowSkill] [bit] NOT NULL DEFAULT (1),
        [AllowAncient] [bit] NOT NULL DEFAULT (0),
        [AncName1] [varchar](50) NULL,
        [AncName2] [varchar](50) NULL,
        [IsActive] [bit] NOT NULL DEFAULT (1),
        CONSTRAINT [PK_WebshopItems] PRIMARY KEY CLUSTERED 
        (
            [ItemType] ASC,
            [ItemIndex] ASC
        )
    )
    PRINT 'Created table: WebshopItems'
END
ELSE
BEGIN
    -- If the table already exists, this safely adds the newer columns we added later!
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[WebshopItems]') AND name = 'AllowAncient')
        ALTER TABLE [dbo].[WebshopItems] ADD [AllowAncient] [bit] NOT NULL DEFAULT (0);
        
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[WebshopItems]') AND name = 'AncName1')
        ALTER TABLE [dbo].[WebshopItems] ADD [AncName1] [varchar](50) NULL;
        
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[WebshopItems]') AND name = 'AncName2')
        ALTER TABLE [dbo].[WebshopItems] ADD [AncName2] [varchar](50) NULL;
        
    PRINT 'Updated table: WebshopItems with latest columns.'
END
GO