USE [master]
GO
/****** Objeto: Database [PruebaTecnicaDB] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE DATABASE [PruebaTecnicaDB]
 CONTAINMENT = NONE
 ON  PRIMARY 
( NAME = N'PruebaTecnicaDB', FILENAME = N'C:\Program Files\Microsoft SQL Server\MSSQL17.SQLEXPRESS\MSSQL\DATA\PruebaTecnicaDB.mdf' , SIZE = 8192KB , MAXSIZE = UNLIMITED, FILEGROWTH = 65536KB )
 LOG ON 
( NAME = N'PruebaTecnicaDB_log', FILENAME = N'C:\Program Files\Microsoft SQL Server\MSSQL17.SQLEXPRESS\MSSQL\DATA\PruebaTecnicaDB_log.ldf' , SIZE = 8192KB , MAXSIZE = 2048GB , FILEGROWTH = 65536KB )
 WITH CATALOG_COLLATION = DATABASE_DEFAULT, LEDGER = OFF
GO
ALTER DATABASE [PruebaTecnicaDB] SET COMPATIBILITY_LEVEL = 170
GO
IF (1 = FULLTEXTSERVICEPROPERTY('IsFullTextInstalled'))
begin
EXEC [PruebaTecnicaDB].[dbo].[sp_fulltext_database] @action = 'enable'
end
GO
ALTER DATABASE [PruebaTecnicaDB] SET ANSI_NULL_DEFAULT OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET ANSI_NULLS OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET ANSI_PADDING OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET ANSI_WARNINGS OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET ARITHABORT OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET AUTO_CLOSE ON 
GO
ALTER DATABASE [PruebaTecnicaDB] SET AUTO_SHRINK OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET AUTO_UPDATE_STATISTICS ON 
GO
ALTER DATABASE [PruebaTecnicaDB] SET CURSOR_CLOSE_ON_COMMIT OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET CURSOR_DEFAULT  GLOBAL 
GO
ALTER DATABASE [PruebaTecnicaDB] SET CONCAT_NULL_YIELDS_NULL OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET NUMERIC_ROUNDABORT OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET QUOTED_IDENTIFIER OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET RECURSIVE_TRIGGERS OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET  ENABLE_BROKER 
GO
ALTER DATABASE [PruebaTecnicaDB] SET AUTO_UPDATE_STATISTICS_ASYNC OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET DATE_CORRELATION_OPTIMIZATION OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET TRUSTWORTHY OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET ALLOW_SNAPSHOT_ISOLATION OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET PARAMETERIZATION SIMPLE 
GO
ALTER DATABASE [PruebaTecnicaDB] SET READ_COMMITTED_SNAPSHOT OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET HONOR_BROKER_PRIORITY OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET RECOVERY SIMPLE 
GO
ALTER DATABASE [PruebaTecnicaDB] SET  MULTI_USER 
GO
ALTER DATABASE [PruebaTecnicaDB] SET PAGE_VERIFY CHECKSUM  
GO
ALTER DATABASE [PruebaTecnicaDB] SET DB_CHAINING OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET FILESTREAM( NON_TRANSACTED_ACCESS = OFF ) 
GO
ALTER DATABASE [PruebaTecnicaDB] SET TARGET_RECOVERY_TIME = 60 SECONDS 
GO
ALTER DATABASE [PruebaTecnicaDB] SET DELAYED_DURABILITY = DISABLED 
GO
ALTER DATABASE [PruebaTecnicaDB] SET OPTIMIZED_LOCKING = OFF 
GO
ALTER DATABASE [PruebaTecnicaDB] SET ACCELERATED_DATABASE_RECOVERY = OFF  
GO
ALTER DATABASE [PruebaTecnicaDB] SET QUERY_STORE = ON
GO
ALTER DATABASE [PruebaTecnicaDB] SET QUERY_STORE (OPERATION_MODE = READ_WRITE, CLEANUP_POLICY = (STALE_QUERY_THRESHOLD_DAYS = 30), DATA_FLUSH_INTERVAL_SECONDS = 900, INTERVAL_LENGTH_MINUTES = 60, MAX_STORAGE_SIZE_MB = 1000, QUERY_CAPTURE_MODE = AUTO, SIZE_BASED_CLEANUP_MODE = AUTO, MAX_PLANS_PER_QUERY = 200, WAIT_STATS_CAPTURE_MODE = ON)
GO
USE [PruebaTecnicaDB]
GO
/****** Objeto: User [prueba_owner] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE USER [prueba_owner] FOR LOGIN [prueba_owner] WITH DEFAULT_SCHEMA=[dbo]
GO
ALTER ROLE [db_owner] ADD MEMBER [prueba_owner]
GO
/****** Objeto: Table [dbo].[alerts] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[alerts](
	[id] [uniqueidentifier] NOT NULL,
	[reading_id] [uniqueidentifier] NOT NULL,
	[type] [nvarchar](20) NOT NULL,
	[message] [nvarchar](max) NOT NULL,
	[created_at] [datetime] NULL,
 CONSTRAINT [alerts_id_primary] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[bodegas] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[bodegas](
	[id] [uniqueidentifier] NOT NULL,
	[name] [nvarchar](60) NOT NULL,
	[description] [nvarchar](max) NULL,
	[status] [bit] NOT NULL,
	[created_by] [bigint] NOT NULL,
	[updated_by] [bigint] NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
	[deleted_at] [datetime] NULL,
 CONSTRAINT [bodegas_id_primary] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[failed_jobs] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[failed_jobs](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[uuid] [nvarchar](255) NOT NULL,
	[connection] [nvarchar](max) NOT NULL,
	[queue] [nvarchar](max) NOT NULL,
	[payload] [nvarchar](max) NOT NULL,
	[exception] [nvarchar](max) NOT NULL,
	[failed_at] [datetime] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[groups] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[groups](
	[id] [uniqueidentifier] NOT NULL,
	[name] [nvarchar](60) NOT NULL,
	[description] [nvarchar](max) NULL,
	[status] [bit] NOT NULL,
	[created_by] [bigint] NOT NULL,
	[updated_by] [bigint] NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
	[deleted_at] [datetime] NULL,
 CONSTRAINT [groups_id_primary] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[lots] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[lots](
	[id] [uniqueidentifier] NOT NULL,
	[name] [nvarchar](120) NOT NULL,
	[product_id] [uniqueidentifier] NOT NULL,
	[warehouse_id] [uniqueidentifier] NOT NULL,
	[stock] [int] NOT NULL,
	[expiration_date] [date] NOT NULL,
	[description] [nvarchar](max) NULL,
	[status] [bit] NOT NULL,
	[created_by] [bigint] NOT NULL,
	[updated_by] [bigint] NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
	[deleted_at] [datetime] NULL,
 CONSTRAINT [lots_id_primary] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[migrations] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[migrations](
	[id] [int] IDENTITY(1,1) NOT NULL,
	[migration] [nvarchar](255) NOT NULL,
	[batch] [int] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[password_resets] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[password_resets](
	[email] [nvarchar](255) NOT NULL,
	[token] [nvarchar](255) NOT NULL,
	[created_at] [datetime] NULL,
 CONSTRAINT [password_resets_email_primary] PRIMARY KEY CLUSTERED 
(
	[email] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[personal_access_tokens] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[personal_access_tokens](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[tokenable_type] [nvarchar](255) NOT NULL,
	[tokenable_id] [bigint] NOT NULL,
	[name] [nvarchar](255) NOT NULL,
	[token] [nvarchar](64) NOT NULL,
	[abilities] [nvarchar](max) NULL,
	[last_used_at] [datetime] NULL,
	[expires_at] [datetime] NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[products] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[products](
	[id] [uniqueidentifier] NOT NULL,
	[name] [nvarchar](100) NOT NULL,
	[cum] [nvarchar](30) NOT NULL,
	[barcode] [nvarchar](60) NULL,
	[invima_registration] [nvarchar](60) NOT NULL,
	[group_id] [uniqueidentifier] NOT NULL,
	[unit_id] [uniqueidentifier] NOT NULL,
	[description] [nvarchar](max) NULL,
	[status] [bit] NOT NULL,
	[created_by] [bigint] NOT NULL,
	[updated_by] [bigint] NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
	[deleted_at] [datetime] NULL,
 CONSTRAINT [products_id_primary] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[readings] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[readings](
	[id] [uniqueidentifier] NOT NULL,
	[sensor_id] [uniqueidentifier] NOT NULL,
	[temperature] [decimal](5, 2) NOT NULL,
	[humidity] [decimal](5, 2) NOT NULL,
	[recorded_at] [datetime] NOT NULL,
 CONSTRAINT [readings_id_primary] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[sensors] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[sensors](
	[id] [uniqueidentifier] NOT NULL,
	[name] [nvarchar](60) NOT NULL,
	[description] [nvarchar](max) NULL,
	[status] [bit] NOT NULL,
	[warehouse_id] [uniqueidentifier] NOT NULL,
	[created_by] [bigint] NOT NULL,
	[updated_by] [bigint] NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
	[deleted_at] [datetime] NULL,
 CONSTRAINT [sensors_id_primary] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[units] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[units](
	[id] [uniqueidentifier] NOT NULL,
	[name] [nvarchar](50) NOT NULL,
	[abbreviation] [nvarchar](10) NOT NULL,
	[status] [bit] NOT NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
 CONSTRAINT [units_id_primary] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Objeto: Table [dbo].[users] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[users](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[name] [nvarchar](255) NOT NULL,
	[email] [nvarchar](255) NOT NULL,
	[email_verified_at] [datetime] NULL,
	[password] [nvarchar](255) NOT NULL,
	[remember_token] [nvarchar](100) NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Objeto: Index [alerts_reading_id_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [alerts_reading_id_index] ON [dbo].[alerts]
(
	[reading_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [bodegas_deleted_at_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [bodegas_deleted_at_index] ON [dbo].[bodegas]
(
	[deleted_at] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [bodegas_status_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [bodegas_status_index] ON [dbo].[bodegas]
(
	[status] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [failed_jobs_uuid_unique] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE UNIQUE NONCLUSTERED INDEX [failed_jobs_uuid_unique] ON [dbo].[failed_jobs]
(
	[uuid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, IGNORE_DUP_KEY = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [groups_name_unique] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE UNIQUE NONCLUSTERED INDEX [groups_name_unique] ON [dbo].[groups]
(
	[name] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, IGNORE_DUP_KEY = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [lots_name_unique] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE UNIQUE NONCLUSTERED INDEX [lots_name_unique] ON [dbo].[lots]
(
	[name] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, IGNORE_DUP_KEY = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [lots_product_id_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [lots_product_id_index] ON [dbo].[lots]
(
	[product_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [lots_warehouse_id_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [lots_warehouse_id_index] ON [dbo].[lots]
(
	[warehouse_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [personal_access_tokens_token_unique] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE UNIQUE NONCLUSTERED INDEX [personal_access_tokens_token_unique] ON [dbo].[personal_access_tokens]
(
	[token] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, IGNORE_DUP_KEY = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [personal_access_tokens_tokenable_type_tokenable_id_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [personal_access_tokens_tokenable_type_tokenable_id_index] ON [dbo].[personal_access_tokens]
(
	[tokenable_type] ASC,
	[tokenable_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [products_barcode_unique] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE UNIQUE NONCLUSTERED INDEX [products_barcode_unique] ON [dbo].[products]
(
	[barcode] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, IGNORE_DUP_KEY = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [products_cum_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [products_cum_index] ON [dbo].[products]
(
	[cum] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [products_cum_unique] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE UNIQUE NONCLUSTERED INDEX [products_cum_unique] ON [dbo].[products]
(
	[cum] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, IGNORE_DUP_KEY = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [products_deleted_at_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [products_deleted_at_index] ON [dbo].[products]
(
	[deleted_at] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [products_invima_registration_unique] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE UNIQUE NONCLUSTERED INDEX [products_invima_registration_unique] ON [dbo].[products]
(
	[invima_registration] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, IGNORE_DUP_KEY = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [products_status_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [products_status_index] ON [dbo].[products]
(
	[status] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [readings_recorded_at_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [readings_recorded_at_index] ON [dbo].[readings]
(
	[recorded_at] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [readings_sensor_id_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [readings_sensor_id_index] ON [dbo].[readings]
(
	[sensor_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [sensors_deleted_at_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [sensors_deleted_at_index] ON [dbo].[sensors]
(
	[deleted_at] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [sensors_name_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [sensors_name_index] ON [dbo].[sensors]
(
	[name] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [sensors_status_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [sensors_status_index] ON [dbo].[sensors]
(
	[status] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
/****** Objeto: Index [sensors_warehouse_id_index] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE NONCLUSTERED INDEX [sensors_warehouse_id_index] ON [dbo].[sensors]
(
	[warehouse_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Objeto: Index [users_email_unique] Fecha de script: 15/07/2026 4:38:05 p. m. ******/
CREATE UNIQUE NONCLUSTERED INDEX [users_email_unique] ON [dbo].[users]
(
	[email] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, IGNORE_DUP_KEY = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
ALTER TABLE [dbo].[bodegas] ADD  DEFAULT ('1') FOR [status]
GO
ALTER TABLE [dbo].[failed_jobs] ADD  DEFAULT (getdate()) FOR [failed_at]
GO
ALTER TABLE [dbo].[groups] ADD  DEFAULT ('1') FOR [status]
GO
ALTER TABLE [dbo].[lots] ADD  DEFAULT ('0') FOR [stock]
GO
ALTER TABLE [dbo].[lots] ADD  DEFAULT ('1') FOR [status]
GO
ALTER TABLE [dbo].[products] ADD  DEFAULT ('1') FOR [status]
GO
ALTER TABLE [dbo].[sensors] ADD  DEFAULT ('1') FOR [status]
GO
ALTER TABLE [dbo].[units] ADD  DEFAULT ('1') FOR [status]
GO
ALTER TABLE [dbo].[alerts]  WITH CHECK ADD  CONSTRAINT [alerts_reading_id_foreign] FOREIGN KEY([reading_id])
REFERENCES [dbo].[readings] ([id])
ON DELETE CASCADE
GO
ALTER TABLE [dbo].[alerts] CHECK CONSTRAINT [alerts_reading_id_foreign]
GO
ALTER TABLE [dbo].[bodegas]  WITH CHECK ADD  CONSTRAINT [bodegas_created_by_foreign] FOREIGN KEY([created_by])
REFERENCES [dbo].[users] ([id])
GO
ALTER TABLE [dbo].[bodegas] CHECK CONSTRAINT [bodegas_created_by_foreign]
GO
ALTER TABLE [dbo].[bodegas]  WITH CHECK ADD  CONSTRAINT [bodegas_updated_by_foreign] FOREIGN KEY([updated_by])
REFERENCES [dbo].[users] ([id])
ON DELETE SET NULL
GO
ALTER TABLE [dbo].[bodegas] CHECK CONSTRAINT [bodegas_updated_by_foreign]
GO
ALTER TABLE [dbo].[groups]  WITH CHECK ADD  CONSTRAINT [groups_created_by_foreign] FOREIGN KEY([created_by])
REFERENCES [dbo].[users] ([id])
GO
ALTER TABLE [dbo].[groups] CHECK CONSTRAINT [groups_created_by_foreign]
GO
ALTER TABLE [dbo].[groups]  WITH CHECK ADD  CONSTRAINT [groups_updated_by_foreign] FOREIGN KEY([updated_by])
REFERENCES [dbo].[users] ([id])
ON DELETE SET NULL
GO
ALTER TABLE [dbo].[groups] CHECK CONSTRAINT [groups_updated_by_foreign]
GO
ALTER TABLE [dbo].[lots]  WITH CHECK ADD  CONSTRAINT [lots_created_by_foreign] FOREIGN KEY([created_by])
REFERENCES [dbo].[users] ([id])
GO
ALTER TABLE [dbo].[lots] CHECK CONSTRAINT [lots_created_by_foreign]
GO
ALTER TABLE [dbo].[lots]  WITH CHECK ADD  CONSTRAINT [lots_product_id_foreign] FOREIGN KEY([product_id])
REFERENCES [dbo].[products] ([id])
GO
ALTER TABLE [dbo].[lots] CHECK CONSTRAINT [lots_product_id_foreign]
GO
ALTER TABLE [dbo].[lots]  WITH CHECK ADD  CONSTRAINT [lots_updated_by_foreign] FOREIGN KEY([updated_by])
REFERENCES [dbo].[users] ([id])
ON DELETE SET NULL
GO
ALTER TABLE [dbo].[lots] CHECK CONSTRAINT [lots_updated_by_foreign]
GO
ALTER TABLE [dbo].[lots]  WITH CHECK ADD  CONSTRAINT [lots_warehouse_id_foreign] FOREIGN KEY([warehouse_id])
REFERENCES [dbo].[bodegas] ([id])
GO
ALTER TABLE [dbo].[lots] CHECK CONSTRAINT [lots_warehouse_id_foreign]
GO
ALTER TABLE [dbo].[products]  WITH CHECK ADD  CONSTRAINT [products_created_by_foreign] FOREIGN KEY([created_by])
REFERENCES [dbo].[users] ([id])
GO
ALTER TABLE [dbo].[products] CHECK CONSTRAINT [products_created_by_foreign]
GO
ALTER TABLE [dbo].[products]  WITH CHECK ADD  CONSTRAINT [products_group_id_foreign] FOREIGN KEY([group_id])
REFERENCES [dbo].[groups] ([id])
GO
ALTER TABLE [dbo].[products] CHECK CONSTRAINT [products_group_id_foreign]
GO
ALTER TABLE [dbo].[products]  WITH CHECK ADD  CONSTRAINT [products_unit_id_foreign] FOREIGN KEY([unit_id])
REFERENCES [dbo].[units] ([id])
GO
ALTER TABLE [dbo].[products] CHECK CONSTRAINT [products_unit_id_foreign]
GO
ALTER TABLE [dbo].[products]  WITH CHECK ADD  CONSTRAINT [products_updated_by_foreign] FOREIGN KEY([updated_by])
REFERENCES [dbo].[users] ([id])
ON DELETE SET NULL
GO
ALTER TABLE [dbo].[products] CHECK CONSTRAINT [products_updated_by_foreign]
GO
ALTER TABLE [dbo].[readings]  WITH CHECK ADD  CONSTRAINT [readings_sensor_id_foreign] FOREIGN KEY([sensor_id])
REFERENCES [dbo].[sensors] ([id])
ON DELETE CASCADE
GO
ALTER TABLE [dbo].[readings] CHECK CONSTRAINT [readings_sensor_id_foreign]
GO
ALTER TABLE [dbo].[sensors]  WITH CHECK ADD  CONSTRAINT [sensors_created_by_foreign] FOREIGN KEY([created_by])
REFERENCES [dbo].[users] ([id])
GO
ALTER TABLE [dbo].[sensors] CHECK CONSTRAINT [sensors_created_by_foreign]
GO
ALTER TABLE [dbo].[sensors]  WITH CHECK ADD  CONSTRAINT [sensors_updated_by_foreign] FOREIGN KEY([updated_by])
REFERENCES [dbo].[users] ([id])
ON DELETE SET NULL
GO
ALTER TABLE [dbo].[sensors] CHECK CONSTRAINT [sensors_updated_by_foreign]
GO
ALTER TABLE [dbo].[sensors]  WITH CHECK ADD  CONSTRAINT [sensors_warehouse_id_foreign] FOREIGN KEY([warehouse_id])
REFERENCES [dbo].[bodegas] ([id])
GO
ALTER TABLE [dbo].[sensors] CHECK CONSTRAINT [sensors_warehouse_id_foreign]
GO
USE [master]
GO
ALTER DATABASE [PruebaTecnicaDB] SET  READ_WRITE 
GO
