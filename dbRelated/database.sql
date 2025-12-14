-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 14, 2025 at 05:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `employee_leave_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `account`
--

CREATE TABLE `account` (
  `AccountID` int(11) NOT NULL,
  `EmployeeID` varchar(20) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('Instructor','Department Head','Dean','Admin') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account`
--

INSERT INTO `account` (`AccountID`, `EmployeeID`, `Username`, `Password`, `Role`) VALUES
(1, '202403655', 'mark', '$2y$10$w02z0wnzxrTDwNP6T5oqpusTkYU9LwBK2UUh1TYud5GOn.p8yB8pe', 'Admin'),
(15, '202103566', 'MarkUser', '$2y$10$L99.T6kFXpb6RYONK7i6pu8Iz08y9gqmyVqewP.FON3mGs94LZPv.', 'Department Head');

-- --------------------------------------------------------

--
-- Table structure for table `adminregistration`
--

CREATE TABLE `adminregistration` (
  `ID` int(11) NOT NULL,
  `EmployeeID` varchar(20) NOT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `status` enum('Active','Inactive','Pending') NOT NULL,
  `DateAdded` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adminregistration`
--

INSERT INTO `adminregistration` (`ID`, `EmployeeID`, `Email`, `status`, `DateAdded`) VALUES
(9, '202403655', 'andomark922@gmail.com', 'Active', '2025-10-26 12:09:59'),
(30, '202103566', 'ae202403655@wmsu.edu.ph', 'Active', '2025-12-13 09:25:27'),
(33, '202503655', 'markando833@gmail.com', 'Active', '2025-12-13 16:57:09');

-- --------------------------------------------------------

--
-- Table structure for table `auditlog`
--

CREATE TABLE `auditlog` (
  `LogID` int(11) NOT NULL,
  `Timestamp` datetime DEFAULT current_timestamp(),
  `EmployeeID_Performer` varchar(20) DEFAULT NULL,
  `EventType` varchar(100) NOT NULL,
  `SourceTable` varchar(50) NOT NULL,
  `SourceRecordID` varchar(50) NOT NULL,
  `OldData` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`OldData`)),
  `NewData` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`NewData`)),
  `Remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `DepartmentID` int(11) NOT NULL,
  `DepartmentName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`DepartmentID`, `DepartmentName`) VALUES
(1, 'College Of Liberal Arts'),
(2, 'College of Teacher Education'),
(3, 'College of Nursing'),
(4, 'College of Engineering'),
(5, 'College of Home Economics'),
(6, 'College of Computing Studies'),
(7, 'College of Public Administration'),
(8, 'College of Law'),
(9, 'College of Sports Sciences and Physical Education'),
(10, 'College of Architecture'),
(11, 'College of Science and Mathematics'),
(12, 'College of Criminal Justice Education'),
(13, 'College of Asian Islamic Studies'),
(14, 'College of Social Work and Community Development'),
(15, 'College of Medicine');

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `EmployeeID` varchar(20) NOT NULL,
  `FirstName` varchar(50) DEFAULT NULL,
  `MiddleName` varchar(50) DEFAULT NULL,
  `LastName` varchar(50) DEFAULT NULL,
  `DOB` date NOT NULL,
  `Sex` enum('Male','Female') NOT NULL,
  `ContactNumber` varchar(15) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `DepartmentID` int(11) DEFAULT NULL,
  `PositionID` int(11) DEFAULT NULL,
  `DateHired` date NOT NULL,
  `isTeaching` varchar(10) DEFAULT NULL,
  `profilePic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`EmployeeID`, `FirstName`, `MiddleName`, `LastName`, `DOB`, `Sex`, `ContactNumber`, `Email`, `DepartmentID`, `PositionID`, `DateHired`, `isTeaching`, `profilePic`) VALUES
('202103566', 'Mark', 'Salas', 'Ando', '1991-02-21', 'Male', '09351153438', 'ae202403655@wmsu.edu.ph', 6, 3, '2017-06-13', '1', 'uploadedFiles/profile/1765589437_download1.jpg'),
('202403655', 'Mark', 'N/A', 'Ando', '2005-07-09', 'Male', '09351153438', 'andomark922@gmail.com', 1, 4, '2020-01-10', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `jobposition`
--

CREATE TABLE `jobposition` (
  `PositionID` int(11) NOT NULL,
  `PositionName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobposition`
--

INSERT INTO `jobposition` (`PositionID`, `PositionName`) VALUES
(1, 'Faculty'),
(2, 'Department Head'),
(3, 'Dean'),
(4, 'Admin');

-- --------------------------------------------------------

--
-- Table structure for table `leaveapplication`
--

CREATE TABLE `leaveapplication` (
  `LeaveID` varchar(10) NOT NULL,
  `EmployeeID` varchar(20) NOT NULL,
  `LeaveTypeID` int(11) NOT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date NOT NULL,
  `NumberOfDays` decimal(5,2) NOT NULL,
  `Reason` text NOT NULL,
  `Attachment` varchar(255) DEFAULT NULL,
  `Priority` enum('High','Normal') DEFAULT 'Normal',
  `Status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `DateApplied` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leaveapplication`
--

INSERT INTO `leaveapplication` (`LeaveID`, `EmployeeID`, `LeaveTypeID`, `StartDate`, `EndDate`, `NumberOfDays`, `Reason`, `Attachment`, `Priority`, `Status`, `DateApplied`) VALUES
('241796', '202103566', 1, '2025-12-15', '2025-12-26', 10.00, 'testing modular 1 vaca', NULL, 'Normal', 'Approved', '2025-12-13 00:00:00'),
('270534', '202403655', 2, '2025-12-18', '2025-12-23', 4.00, 'sicktest 1', 'uploadedFiles/1765585842_MobileAppDevelopmentProjectGuidelines.pdf', 'Normal', 'Approved', '2025-12-13 00:00:00'),
('458838', '202403655', 1, '2025-12-15', '2025-12-15', 1.00, 'testing 2 with gmail', NULL, 'Normal', 'Approved', '2025-12-13 00:00:00'),
('795040', '202403655', 1, '2025-12-15', '2025-12-15', 1.00, 'testing 1', NULL, 'Normal', 'Approved', '2025-12-13 00:00:00'),
('829792', '202403655', 2, '2025-12-18', '2025-12-23', 4.00, 'testing sick with gmail and credit update current 59', 'uploadedFiles/1765587480_01.IRIJEEAPHYSICO-CHEMICALQUALITIESNUTRITIONFACTSANDSHELF-LIFEEVALUATIONOFTHEDEVELOPEDCOOKIESFLAVOREDWITHTURMERICCurcumalonga51.pdf', 'Normal', 'Approved', '2025-12-13 00:00:00'),
('938584', '202103566', 2, '2025-12-15', '2025-12-26', 10.00, 'Final Testing Day', 'uploadedFiles/1765675938_Pathfit-3.pdf', 'Normal', 'Approved', '2025-12-14 00:00:00'),
('983294', '202103566', 2, '2025-12-24', '2025-12-25', 2.00, 'testing sick of other employee 5 credit', 'uploadedFiles/1765589837_DML_and_DCL_SQL_Tables_Fixed.pdf', 'Normal', 'Approved', '2025-12-13 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `leaveapproval`
--

CREATE TABLE `leaveapproval` (
  `ApprovalID` varchar(10) NOT NULL,
  `LeaveID` varchar(10) NOT NULL,
  `EmployeeApproverID` varchar(20) NOT NULL,
  `ApproverRole` enum('Department Head','Dean','Admin') NOT NULL,
  `Decision` enum('Approved','Rejected','Pending') DEFAULT 'Pending',
  `Remarks` text DEFAULT NULL,
  `DecidedDate` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leaveapproval`
--

INSERT INTO `leaveapproval` (`ApprovalID`, `LeaveID`, `EmployeeApproverID`, `ApproverRole`, `Decision`, `Remarks`, `DecidedDate`) VALUES
('0OU0IGMJ4J', '983294', '202403655', 'Admin', 'Approved', 'testing it with gmail', '2025-12-13 09:40:04'),
('6DTU9IWXSF', '795040', '202403655', 'Admin', 'Approved', 'testing to approve 2', '2025-12-13 08:43:14'),
('B0J1J5LDL1', '241796', '202403655', 'Admin', 'Approved', 'testing', '2025-12-13 17:04:33'),
('E40IDQUASL', '458838', '202403655', 'Admin', 'Approved', 'testing the gmail', '2025-12-13 08:54:26'),
('MTJS0YCOCM', '829792', '202403655', 'Admin', 'Approved', 'testing gmail of sick', '2025-12-13 08:58:35'),
('QOTZ35VOOJ', '938584', '202403655', 'Admin', 'Approved', 'Final Testing Day', '2025-12-14 09:34:00');

-- --------------------------------------------------------

--
-- Table structure for table `leavecredits`
--

CREATE TABLE `leavecredits` (
  `CreditID` int(11) NOT NULL,
  `EmployeeID` varchar(20) NOT NULL,
  `sick` decimal(10,5) DEFAULT 0.00000,
  `vacation` decimal(10,5) DEFAULT 0.00000,
  `lastUpdated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leavecredits`
--

INSERT INTO `leavecredits` (`CreditID`, `EmployeeID`, `sick`, `vacation`, `lastUpdated`) VALUES
(1, '202403655', 55.16667, 86.75000, '2025-12-12 18:59:49'),
(2, '202103566', 73.00000, 117.50000, '2025-12-13 09:30:37');

-- --------------------------------------------------------

--
-- Table structure for table `leavetype`
--

CREATE TABLE `leavetype` (
  `LeaveTypeID` int(11) NOT NULL,
  `TypeName` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leavetype`
--

INSERT INTO `leavetype` (`LeaveTypeID`, `TypeName`) VALUES
(2, 'Sick'),
(1, 'Vacation');

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `NotificationID` int(11) NOT NULL,
  `EmployeeID` varchar(20) NOT NULL,
  `Status` enum('unread','read') DEFAULT 'unread',
  `Purpose` varchar(50) NOT NULL,
  `Title` varchar(255) DEFAULT 'New Action Required',
  `Message` text DEFAULT NULL,
  `Link` varchar(255) DEFAULT NULL,
  `DateCreated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`NotificationID`, `EmployeeID`, `Status`, `Purpose`, `Title`, `Message`, `Link`, `DateCreated`) VALUES
(1, '202403655', 'read', 'apply', 'New Action Required', NULL, NULL, '2025-12-13 08:28:05'),
(2, '202403655', 'read', 'apply', 'New Action Required', NULL, NULL, '2025-12-13 08:30:42'),
(3, '202403655', 'read', 'apply', 'New Action Required', NULL, NULL, '2025-12-13 08:54:10'),
(4, '202403655', 'read', 'apply', 'New Action Required', NULL, NULL, '2025-12-13 08:58:00'),
(5, '202103566', 'read', 'apply', 'New Action Required', NULL, NULL, '2025-12-13 09:37:17'),
(6, '202103566', 'read', 'apply', 'New Action Required', NULL, NULL, '2025-12-13 17:02:52'),
(7, '202103566', 'read', 'apply', 'New Action Required', NULL, NULL, '2025-12-14 09:32:18');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `ResetID` int(11) NOT NULL,
  `AccountID` int(11) NOT NULL,
  `OTP_Code` varchar(6) NOT NULL,
  `ResetExpiry` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account`
--
ALTER TABLE `account`
  ADD PRIMARY KEY (`AccountID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `EmployeeID` (`EmployeeID`);

--
-- Indexes for table `adminregistration`
--
ALTER TABLE `adminregistration`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `EmployeeID` (`EmployeeID`);

--
-- Indexes for table `auditlog`
--
ALTER TABLE `auditlog`
  ADD PRIMARY KEY (`LogID`),
  ADD KEY `EmployeeID_Performer` (`EmployeeID_Performer`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`DepartmentID`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`EmployeeID`),
  ADD KEY `DepartmentID` (`DepartmentID`),
  ADD KEY `PositionID` (`PositionID`);

--
-- Indexes for table `jobposition`
--
ALTER TABLE `jobposition`
  ADD PRIMARY KEY (`PositionID`);

--
-- Indexes for table `leaveapplication`
--
ALTER TABLE `leaveapplication`
  ADD PRIMARY KEY (`LeaveID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `fk_leaveapplication_leavetype` (`LeaveTypeID`);

--
-- Indexes for table `leaveapproval`
--
ALTER TABLE `leaveapproval`
  ADD PRIMARY KEY (`ApprovalID`),
  ADD KEY `LeaveID` (`LeaveID`),
  ADD KEY `EmployeeApproverID` (`EmployeeApproverID`);

--
-- Indexes for table `leavecredits`
--
ALTER TABLE `leavecredits`
  ADD PRIMARY KEY (`CreditID`),
  ADD UNIQUE KEY `uk_employee_credit` (`EmployeeID`);

--
-- Indexes for table `leavetype`
--
ALTER TABLE `leavetype`
  ADD PRIMARY KEY (`LeaveTypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`NotificationID`),
  ADD KEY `EmployeeID` (`EmployeeID`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`ResetID`),
  ADD UNIQUE KEY `OTP_Code` (`OTP_Code`),
  ADD KEY `fk_account_id` (`AccountID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account`
--
ALTER TABLE `account`
  MODIFY `AccountID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `adminregistration`
--
ALTER TABLE `adminregistration`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `auditlog`
--
ALTER TABLE `auditlog`
  MODIFY `LogID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `jobposition`
--
ALTER TABLE `jobposition`
  MODIFY `PositionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leavecredits`
--
ALTER TABLE `leavecredits`
  MODIFY `CreditID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leavetype`
--
ALTER TABLE `leavetype`
  MODIFY `LeaveTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `NotificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `ResetID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account`
--
ALTER TABLE `account`
  ADD CONSTRAINT `account_ibfk_1` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `auditlog`
--
ALTER TABLE `auditlog`
  ADD CONSTRAINT `auditlog_ibfk_1` FOREIGN KEY (`EmployeeID_Performer`) REFERENCES `employee` (`EmployeeID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `employee`
--
ALTER TABLE `employee`
  ADD CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`EmployeeID`) REFERENCES `adminregistration` (`EmployeeID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `employee_ibfk_2` FOREIGN KEY (`DepartmentID`) REFERENCES `department` (`DepartmentID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `employee_ibfk_3` FOREIGN KEY (`PositionID`) REFERENCES `jobposition` (`PositionID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `leaveapplication`
--
ALTER TABLE `leaveapplication`
  ADD CONSTRAINT `fk_leaveapplication_leavetype` FOREIGN KEY (`LeaveTypeID`) REFERENCES `leavetype` (`LeaveTypeID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `leaveapplication_ibfk_1` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `leaveapproval`
--
ALTER TABLE `leaveapproval`
  ADD CONSTRAINT `leaveapproval_ibfk_2` FOREIGN KEY (`EmployeeApproverID`) REFERENCES `employee` (`EmployeeID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `leavecredits`
--
ALTER TABLE `leavecredits`
  ADD CONSTRAINT `fk_credits_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `fk_notification_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_account_id` FOREIGN KEY (`AccountID`) REFERENCES `account` (`AccountID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
