<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/includes/db.php'; // আপনার ডাটাবেজ কানেকশন ফাইল

$json_data = '{
    "status": true,
    "total_games": 94,
    "games": [
        { "brand_id": "45", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/pgsoft__eSCGuSud.png" },
        { "brand_id": "46", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/sabasports-ibc_jLnyoDVBx.jpg" },
        { "brand_id": "48", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/unitedgaming_mNkr4Q5Lu.png" },
        { "brand_id": "49", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/jili_zCoKdKI0w.png" },
        { "brand_id": "50", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/jdb_bfN7Li8aT.svg" },
        { "brand_id": "51", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/tadagaming_VODQcWMhZ.png" },
        { "brand_id": "52", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/cq9_GMWnfJS6j.png" },
        { "brand_id": "53", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_53_1759738767_0RTFI0ruM.png" },
        { "brand_id": "54", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_54_1759738767_XUvlduUi2.png" },
        { "brand_id": "55", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_55_1759738768_pO-J--EsT.png" },
        { "brand_id": "56", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_56_1759738767_X-OOEWVG3.png" },
        { "brand_id": "57", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/spribe_PT57NjKD1.png" },
        { "brand_id": "58", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_58_1759739497_u136bxtGP.png" },
        { "brand_id": "59", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_59_1759739498_kDA8fpQtV.png" },
        { "brand_id": "60", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/yeebet_Qkd_HP7B1.png" },
        { "brand_id": "61", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/fachaigaming_XDlsvQLjm.png" },
        { "brand_id": "62", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/bigtimegaming_R1n1_ixJN.jpg" },
        { "brand_id": "63", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/bigtimegaming-asia_iJ_C78lGQ.jpg" },
        { "brand_id": "64", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/gameart_NBb8dzy_S.png" },
        { "brand_id": "65", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/bgaming_pdaAU3WLj.png" },
        { "brand_id": "66", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/nolimit-city-asia_Bw3lMxQ63.png" },
        { "brand_id": "67", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/nolimit-city_dGZqMlHEI.png" },
        { "brand_id": "68", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/netent_PcottkcL9.png" },
        { "brand_id": "69", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_69_1759742598_NnaIvF82-.png" },
        { "brand_id": "70", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/relaxgaming_-Yg8pOo7k.png" },
        { "brand_id": "71", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_71_1759745923_jGiWGxkyY.png" },
        { "brand_id": "72", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/playtech_kh3OoJoCZ.svg" },
        { "brand_id": "73", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/playngo_M64H004gH.png" },
        { "brand_id": "74", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/redtiger_GckNg_i4e.png" },
        { "brand_id": "75", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_75_1759742711_p_BDBA0F5.png" },
        { "brand_id": "76", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/playson_TVurEdDE9.png" },
        { "brand_id": "77", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/evoplay_1A4Zz15L6.png" },
        { "brand_id": "78", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/ezugi_YOoNA87AJ.png" },
        { "brand_id": "79", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/ideal_JfiVSmlvK.png" },
        { "brand_id": "80", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/t1_SVPjMoD7a.png" },
        { "brand_id": "81", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/playace-aggaming_IVBgkzHHl.png" },
        { "brand_id": "82", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/astar_qR5tTKi4v.png" },
        { "brand_id": "83", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_83_1759747195_Atqb52OM-.png" },
        { "brand_id": "84", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/rich88_UywTBtIVM.png" },
        { "brand_id": "85", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/tf_Hfhq23ykw.png" },
        { "brand_id": "86", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_86_1759753285_gXkNIjtpb.webp" },
        { "brand_id": "87", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/dreamgaming_orGaXRnvq.jpg" },
        { "brand_id": "88", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/sexy_6tieOBYXp.png" },
        { "brand_id": "89", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/sagaming_3lTI1C8VE.png" },
        { "brand_id": "90", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/microgaming_mm5ILoG4V.png" },
        { "brand_id": "91", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/habanero_TzgioUNJE.svg" },
        { "brand_id": "92", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/ygrgaming_TbLfUpZzc.jpg" },
        { "brand_id": "93", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/koolbet_GfoRDo30w.jpg" },
        { "brand_id": "94", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/dpesports_oL4ejT2g1.png" },
        { "brand_id": "95", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/dpsports_6EVgDlxnv.png" },
        { "brand_id": "96", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_96_1759754794_tsdHQmqWO.PNG" },
        { "brand_id": "97", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/hacksaw-asia_oCM4030vb.png" },
        { "brand_id": "98", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/hacksaw-latam_vKVa1IAKd.png" },
        { "brand_id": "99", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/hacksaw-world_DzjKeVGna.png" },
        { "brand_id": "100", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/turbogames-asia_x-LY05KTt.png" },
        { "brand_id": "101", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/turbogames-world_hhq2EG8ZD.png" },
        { "brand_id": "102", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/onegaming_US8ajzcPi.png" },
        { "brand_id": "103", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/5ggaming_tTaDa1S0F.png" },
        { "brand_id": "104", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/mini_pVhCJw440.png" },
        { "brand_id": "105", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_105_1759755216_d8ysyB9Bd.png" },
        { "brand_id": "106", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/epicwin_cHPP34SvY.png" },
        { "brand_id": "107", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/smartsoft_pqTVVLSgg.png" },
        { "brand_id": "108", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_108_1759755400_NoNC975dE.PNG" },
        { "brand_id": "109", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/btgaming_4Sg3NMWJ-.png" },
        { "brand_id": "110", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/pix_Sk-nLiaKt.png" },
        { "brand_id": "111", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_111_1759755586_QYQ6oiwvw.png" },
        { "brand_id": "112", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/inout_L95ybT6Vl.svg" },
        { "brand_id": "113", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_113_1759757270_KyAOcxq5j.webp" },
        { "brand_id": "114", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1759580526-wm-casino_gfuzIsjHR.png" },
        { "brand_id": "117", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1759584633-headerLogo_ovoTgi_ge.png" },
        { "brand_id": "118", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1759690799-images_A5pMrF4oS.png" },
        { "brand_id": "119", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1759745485-kingmidas_hnP8rc8ZJ.png" },
        { "brand_id": "120", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1759746283-kingmidas_Oqu0zjno0.png" },
        { "brand_id": "121", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_121_1759746876_jnFiPaUL5b.png" },
        { "brand_id": "122", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/brand_122_1759749392_NEAf74Igf.png" },
        { "brand_id": "123", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1759749692-bc55-game-brand-logo-pegasus_8ltp7Dy3l.png" },
        { "brand_id": "124", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1759755996-vvcvcv_ffim_MyzU.PNG" },
        { "brand_id": "125", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1759756425-sdsdds__z5wLkmya.PNG" },
        { "brand_id": "126", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1759756460-scxxc_oxD99dBJS6.PNG" },
        { "brand_id": "128", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770665380-askmeslot_logo_-OY4t2_NYB.webp" },
        { "brand_id": "129", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770666052-vpus__nilYIAg6B.png" },
        { "brand_id": "130", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770666456-casini-black-text-logo.cde821af64f85d0da29386a6985e2a8f_-mnrWVgfL.svg" },
        { "brand_id": "131", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770666578-100HPWHITE-removebg-preview_hJt231z9y.webp" },
        { "brand_id": "132", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770666928-t_zsSn-g7oM.png" },
        { "brand_id": "133", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770667025-Screenshot_2026-02-10_012653_MHJo17sYY.png" },
        { "brand_id": "134", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770667178-logo-cg-game_tLSGmLofX.png" },
        { "brand_id": "135", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770667442-clow_pau_wHSEuDeuk.png" },
        { "brand_id": "136", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770667786-rubyplay_logo_01_colour_alt_YQ9e9e-zP.webp" },
        { "brand_id": "137", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770667934-logo_i_pIoQF60.svg" },
        { "brand_id": "138", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1770668042-logo__11__hGN9bryFr.png" },
        { "brand_id": "139", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1773393816-aviatrix-1_1__jkxRgnYAr.png" },
        { "brand_id": "140", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1773760761-images_VsVsANb4k.png" },
        { "brand_id": "141", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1774284124-9wicketslogo_1__nHSY_O0YU.webp" },
        { "brand_id": "142", "logo": "https://ik.imagekit.io/f4rqxekfu/brands/1774286045-C_001__CMD_HGxx3eznn.png" }
    ]
}';

$data = json_decode($json_data, true);
$count = 0;

if ($data && isset($data['games'])) {
    foreach ($data['games'] as $item) {
        $brand_id = $conn->real_escape_string($item['brand_id']);
        $new_logo = $conn->real_escape_string($item['logo']);

        // ডাটাবেজ আপডেট কুয়েরি
        $sql = "UPDATE game_providers SET image = '$new_logo' WHERE provider_id = '$brand_id'";
        
        if ($conn->query($sql)) {
            $count++;
            echo "Updated Brand ID $brand_id: Success<br>";
        } else {
            echo "Error updating Brand ID $brand_id: " . $conn->error . "<br>";
        }
    }
    echo "<br><b>Total $count providers updated successfully!</b>";
} else {
    echo "Invalid JSON data.";
}
?>