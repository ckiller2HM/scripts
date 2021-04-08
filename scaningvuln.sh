#!/bin/bash

# Author: Jorge Cedillo (aka D35truck5)

#Varialbes Globales
#DIA=`date +%d/%m/%Y`
#HORA=`date +%H:%M:%S`


#Colours
greenColour="\e[0;32m\033[1m"
endColour="\033[0m\e[0m"
redColour="\e[0;31m\033[1m"
blueColour="\e[0;34m\033[1m"
yellowColour="\e[0;33m\033[1m"
purpleColour="\e[0;35m\033[1m"
turquoiseColour="\e[0;36m\033[1m"
grayColour="\e[0;37m\033[1m"

trap ctrl_c INT

function ctrl_c(){
	echo -e "\n${redColour}[!] Saliendo ...\n${endColour}"
	tput cnorm; exit 1
}

function helpPanel(){
	echo -e "\n${redColour}[!] Uso: ./scaningVuln.sh -i input_File -t type_Scanning ${endColour}"
	for i in $(seq 1 80); do echo -ne "${redColour}-";done; echo -ne "${endColour}";
	echo -e "\n\n\t${grayColour}[ ** ]${endColour}${yellowColour} Tipo de Escaneo${endColour}"
	echo -e "\t\t${purpleColour}[-1]${endColour}${yellowColour}:\t Verificar Host Alcanzable${endColour}"
	echo -e "\t\t${purpleColour}[-2]${endColour}${yellowColour}:\t System Discovery Services${endColour}"
	echo -e "\n\t${grayColour}[ -h ]${endColour}${yellowColour} Mostrar este panel de ayuda${endColour}\n"
	tput cnorm; exit 1
}

#Varialbes Globales
#DIA=`date +%d/%m/%Y`
#HORA=`date +%H:%M:%S`

parameter_counter=0; while getopts "i:t:h:" arg;do
	case $arg in
		i)input_file=$OPTARG; let parameter_counter+=1;;
		t)type_scaning=$OPTARG; let parameter_counter+=1;;
		h)helpPanel;;
		*) echo -e "${redColour}[!]Opcion no Disponible${endColour}";sleep 2;helpPanel;
	esac
done

tput civis

if [ $parameter_counter -eq 0 ];then
	helpPanel
else
	if [ -e $input_file ]; then

		if [ "$(echo $type_scaning)" -eq 1 ]; then

			echo -e "\t\t${blueColour} Start Checking Host reachability ${endColour}\n"

			while read line; do
				ip="$(grep -oE '[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}' <<< "$line")"
				ping -c 1 $ip &> /dev/null && echo -e "\t${yellowColour}"$ip"${endColour} : ${blueColour}Active${endColour}" || echo -e "\t${yellowColour}"$ip"${endColour} : ${redColour}Inactive${endColour}"
			done < "$input_file"; wait
			echo -e "\n\t${yellowColour} Finish Checking Host reachability ${endColour}${redColour}$DIA - `date +%H:%M:%S`${endColour}"
		elif [ "$(echo $type_scaning)" -eq 2 ]; then
			echo -e "\t\t${blueColour} Start Discovery Servies ${endColour}${endColour}"

			while read line; do
				ip="$(grep -oE '[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}' <<< "$line")"
				port="$(cat <<< "$line"|tr ':' ' '|awk '{print $2}')"
				timeout 5 bash -c "</dev/tcp/$ip/$port" &>/dev/null && echo -e "\t${yellowColour}"$ip" : "$port"${endColour} - ${blueColour}OPEN${endColour}" || echo -e "\t${yellowColour}$ip : $port${endColour} - ${redColour}CLOSE${endColour}"
#				timeout 5 bash -c "echo '' < /dev/tcp/$ip/$port" 2>/dev/null && echo -e "\t${yellowColour}"$ip" : "$port"${endColour} - ${blueColour}OPEN${endColour}" || echo -e "\t${yellowColour}$ip : $port${endColour} - ${redColour}CLOSE${endColour}"

			done < "$input_file"; wait
			echo -e "\n\t${yellowColour} Finish Discovery Servies ${endColour}${endColour}${redColour}$DIA - `date +%H:%M:%S`${endColour}"
			
		else
			echo -e "${redColour}[!]Opcion${endColour}${purpleColour} $type_scaning${endColour}${redColour} Incorrecta${endColour}"
				helpPanel
		fi
	else
		echo -e "${redColour} [!] El Archivo no Existe... ${endColour}"
	fi
fi
