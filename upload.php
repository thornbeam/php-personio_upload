<?php
$firstName = $lastName = $availability = $phoneNumber = $email = $salaryRequirements = $privacyPolicy =
    "";
$company_id;
$authorization_key;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo '
  <link rel="stylesheet" href="./css/custom.css">
  ';

    if (empty($_POST["first_name"])) {
        //echo "First name is required";
        //echo "<br>";
    } else {
        $firstName = test_input($_POST["first_name"]);
        // check if name only contains letters and whitespace
        if (!preg_match("/^[a-zA-Z-' ]*$/", $firstName)) {
            //echo "Only letters and white space allowed";
            //echo "<br>";
            $firstName = "";
        }
    }

    if (empty($_POST["last_name"])) {
        //echo "Last name is required";
        //echo "<br>";
    } else {
        $lastName = test_input($_POST["last_name"]);
        // check if name only contains letters and whitespace
        if (!preg_match("/^[a-zA-Z-' ]*$/", $lastName)) {
            //echo "Only letters and white space allowed";
            //echo "<br>";
            $lastName = "";
        }
    }

    if (empty($_POST["availability"])) {
        $availability = "";
    } else {
        $availability = test_input($_POST["availability"]);
    }

    if (empty($_POST["phone_number"])) {
        //echo "Phone number is required";
        //echo "<br>";
    } else {
        $phoneNumber = test_input($_POST["phone_number"]);
    }

    if (empty($_POST["email"])) {
        //echo "Email is required";
        //echo "<br>";
    } else {
        $email = test_input($_POST["email"]);
        // check if e-mail address is well-formed
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            //echo "Invalid email format";
            //echo "<br>";
            $email = "";
        }
    }

    if (empty($_POST["salary_requirements"])) {
        //echo "Salary requirements are required";
        //echo "<br>";
    } else {
        $salaryRequirements = test_input($_POST["salary_requirements"]);
    }

    $fileCategories = ["cv", "cover-letter", "work-sample", "certificate"];
    $amountFiles = 0;
    $uploadReady = false;

    function validateFile($fileInput)
    {
        if ($fileInput["size"] > 20000000) {
            //echo "Sorry, your file is too large.";
            //echo "<br>";
            return false;
        }

        $fileType = strtolower(
            pathinfo($fileInput["name"], PATHINFO_EXTENSION)
        );
        if ($fileType !== "pdf") {
            //echo "Sorry, only PDF files are allowed.";
            //echo "<br>";
            return false;
        }

        return true;
    }

    $fileOk = true;

    foreach ($fileCategories as $category) {
        if ($category === "cv" || $category === "cover-letter") {
            if ($_FILES[$category]["error"] !== 0) {
                //echo "Your " . $category . " is required";
                //echo "<br>";
                $fileOk = false;
            } else {
                if (validateFile($_FILES[$category])) {
                } else {
                    $fileOk = false;
                }

                if ($fileOk) {
                    $amountFiles++;
                }
            }
        } else {
            if ($_FILES[$category]["error"] === 0) {
                if (validateFile($_FILES[$category])) {
                } else {
                    $fileOk = false;
                }
            }
        }
    }

    //echo "amountFiles: " . $amountFiles;
    //echo "<br>";
    //echo "sizeof fileCategories: " . sizeof($fileCategories);
    //echo "<br>";

    if (!isset($_POST["privacy_policy"])) {
        //echo "Your confirmation is required";
        //echo "<br>";
    }

    if (
        !empty($firstName) &&
        !empty($lastName) &&
        !empty($phoneNumber) &&
        !empty($email) &&
        !empty($salaryRequirements) &&
        isset($_POST["privacy_policy"]) &&
        $amountFiles === 2 &&
        $fileOk
    ) {
        // --- FILE UPLOAD BEGIN --- //
        function generateFileDataForJson($category)
        {
            $target_file_name = $_FILES[$category]["name"];
            $target_file_path = $_FILES[$category]["full_path"];
            $target_file_tmp_name = $_FILES[$category]["tmp_name"];

            // prepare the parameters
            $post_data["file"] = curl_file_create(
                $target_file_tmp_name,
                "application/pdf",
                $category . ".pdf"
            );
            $headers = [
                "Accept: application/json",
                "Content-Type: multipart/form-data",
                "x-company-id: " . $company_id,
                "Authorization: Bearer " . $authorization_key,
            ];

            $request = curl_init(
                "https://api.personio.de/v1/recruiting/applications/documents"
            );
            curl_setopt($request, CURLOPT_POST, 1);
            curl_setopt($request, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);

            $response = curl_exec($request);
            if ($response !== false) {
                $response_array = json_decode($response, true);

                return $response_array;
            }
        }

        $original_filenames = [];
        $uuids = [];

        foreach ($fileCategories as $category) {
            if ($category === "cv" || $category === "cover-letter") {
                $response_array = generateFileDataForJson($category);
                $original_filenames[$category] =
                    $response_array["original_filename"];
                $uuids[$category] = $response_array["uuid"];
            } else {
                if ($_FILES[$category]["error"] === 0) {
                    $response_array = generateFileDataForJson($category);
                    $original_filenames[$category] =
                        $response_array["original_filename"];
                    $uuids[$category] = $response_array["uuid"];
                }
            }
        }
        // --- FILE UPLOAD END --- //

        require_once "vendor/autoload.php";
        $client = new \GuzzleHttp\Client();

        $job_id = test_input($_POST["job_id"]);
        $bodyContent_01 =
            '{"phase":{"type":"system","id":"unassigned"},"first_name":"' .
            $firstName .
            '","last_name":"' .
            $lastName .
            '","job_position_id":' .
            $job_id .
            ', "application_date":"' .
            date("Y-m-d") .
            '","email":"' .
            $email .
            '","files":[{"category":"cv","uuid":"' .
            $uuids["cv"] .
            '","original_filename":"' .
            $original_filenames["cv"] .
            '"},{"category":"cover-letter","uuid":"' .
            $uuids["cover-letter"] .
            '","original_filename":"' .
            $original_filenames["cover-letter"] .
            '"}';
        $bodyContent_02 = "";
        if ($original_filenames["work-sample"]) {
            $bodyContent_02 =
                ',{"category":"work-sample","uuid":"' .
                $uuids["work-sample"] .
                '","original_filename":"' .
                $original_filenames["work-sample"] .
                '"}';
        }
        $bodyContent_03 = "";
        if ($original_filenames["certificate"]) {
            $bodyContent_03 =
                ',{"category":"certificate","uuid":"' .
                $uuids["certificate"] .
                '","original_filename":"' .
                $original_filenames["certificate"] .
                '"}';
        }
        $bodyContent_04 =
            '], "attributes":[{"id":"salary_expectations","value":"' .
            $salaryRequirements .
            '"},{"id":"phone","value":"' .
            $phoneNumber .
            '"},{"id":"available_from","value":"' .
            $availability .
            '"}]}';
        //echo $bodyContent_01 . $bodyContent_02 . $bodyContent_03 . $bodyContent_04;
        //echo "bodyContent ok.";
        //echo "<br>";
        //echo "Can't complete bodyContent, something's wrong.";
        //echo "<br>";

        try {
            $response = $client->request(
                "POST",
                "https://api.personio.de/v1/recruiting/applications",
                [
                    "body" =>
                        $bodyContent_01 .
                        $bodyContent_02 .
                        $bodyContent_03 .
                        $bodyContent_04,
                    "headers" => [
                        "X-Company-ID" => $company_id,
                        "accept" => "application/json",
                        "authorization" => "Bearer " . $authorization_key,
                        "content-type" => "application/json",
                    ],
                ]
            );
            echo $response->getStatusCode();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            echo $e->getResponse()->getStatusCode();
        }
    }
}

function test_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
